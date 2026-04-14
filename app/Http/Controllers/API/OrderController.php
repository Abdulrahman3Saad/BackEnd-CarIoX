<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // ===================================================
    // العميل: إنشاء طلب جديد
    // ✅ DB Transaction + Pessimistic Lock — يمنع Race Condition
    // ===================================================
    public function store(Request $request)
    {
        $request->validate([
            'car_id'                 => 'required|exists:cars,id',
            'full_name'              => 'required|string|max:255',
            'phone'                  => 'required|string|max:20|regex:/^[0-9+\-\s]+$/',
            'governorate'            => 'required|string|max:100',
            'address'                => 'required|string|max:500',
            'expected_delivery_date' => 'required|date|after:today',
            'return_date'            => 'nullable|date|after:expected_delivery_date',
            'with_driver'            => 'nullable|boolean',
            'discount'               => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            // ✅ lockForUpdate يمنع قراءة نفس السيارة من request تاني في نفس الوقت
            $car = Car::with('service')->lockForUpdate()->findOrFail($request->car_id);

            if ($car->status !== 'active' || !$car->is_available) {
                return response()->json(['message' => 'السيارة غير متاحة حالياً'], 422);
            }

            if ($car->user_id === $request->user()->id) {
                return response()->json(['message' => 'لا يمكنك طلب سيارتك الخاصة'], 422);
            }

            $discount = floatval($request->discount ?? 0);

            // ✅ حساب السعر بالـ service type بدل string matching
            $isRent   = $car->isRent();
            if ($isRent && $request->return_date) {
                $days     = max(1, Carbon::parse($request->expected_delivery_date)
                                         ->diffInDays(Carbon::parse($request->return_date)));
                $carPrice = floatval($car->price) * $days;
            } else {
                $carPrice = floatval($car->price);
            }

            // ✅ منع discount أكبر من السعر
            if ($discount >= $carPrice) {
                return response()->json(['message' => 'قيمة الخصم لا يمكن أن تساوي أو تتجاوز سعر السيارة'], 422);
            }

            $totalAmount = $carPrice - $discount;

            // ✅ احجز السيارة داخل نفس الـ transaction
            $car->update(['is_available' => false]);

            $order = Order::create([
                'user_id'                => $request->user()->id,
                'car_id'                 => $car->id,
                'full_name'              => $request->full_name,
                'phone'                  => $request->phone,
                'governorate'            => $request->governorate,
                'address'                => $request->address,
                'expected_delivery_date' => $request->expected_delivery_date,
                'return_date'            => $request->return_date,
                'with_driver'            => $request->boolean('with_driver', false),
                'car_price'              => $carPrice,
                'discount'               => $discount,
                'total_amount'           => $totalAmount,
                'order_status'           => 'pending',
            ]);

            $order->load(['car.images', 'car.service']);
            return response()->json($order, 201);
        });
    }

    // ===================================================
    // العميل: طلباتي
    // ===================================================
    public function myOrders(Request $request)
    {
        $orders = Order::with(['car.images', 'car.service', 'payments'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($orders);
    }

    // ===================================================
    // العميل: إلغاء طلب (pending فقط)
    // ===================================================
    public function cancel(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($order->order_status !== 'pending') {
            return response()->json(['message' => 'لا يمكن إلغاء الطلب بعد موافقة صاحب السيارة'], 422);
        }

        DB::transaction(function () use ($order) {
            Car::where('id', $order->car_id)->update(['is_available' => true]);
            $order->update(['order_status' => 'canceled']);
            Payment::where('order_id', $order->id)
                   ->where('status', 'pending')
                   ->update(['status' => 'failed']);
        });

        return response()->json(['message' => 'تم إلغاء الطلب بنجاح']);
    }

    // ===================================================
    // تفاصيل طلب (العميل أو صاحب السيارة أو الأدمن)
    // ===================================================
    public function show(Request $request, Order $order)
    {
        $user       = $request->user();
        $isCustomer = $order->user_id === $user->id;
        $isCarOwner = Car::where('id', $order->car_id)->where('user_id', $user->id)->exists();
        $isAdmin    = $user->role === 'admin';

        if (!$isCustomer && !$isCarOwner && !$isAdmin) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $order->load(['car.images', 'car.service', 'payments', 'user:id,name,phone,email']);
        return response()->json($order);
    }

    // ===================================================
    // صاحب السيارة: الطلبات الواردة على سياراته
    // ===================================================
    public function incomingOrders(Request $request)
    {
        $carIds = Car::where('user_id', $request->user()->id)->pluck('id');

        $query = Order::with(['user:id,name,phone', 'car.images', 'car.service', 'payments'])
            ->whereIn('car_id', $carIds);

        $status = $request->query('status');
        if ($status === 'active') {
            $query->whereIn('order_status', ['pending', 'confirmed', 'in_progress']);
        } elseif ($status === 'completed') {
            $query->whereIn('order_status', ['completed', 'canceled', 'rejected']);
        }

        $orders = $query->latest()->paginate(15);
        return response()->json($orders);
    }

    // ===================================================
    // صاحب السيارة: قبول الطلب
    // ===================================================
    public function approve(Request $request, Order $order)
    {
        $car = Car::findOrFail($order->car_id);

        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($order->order_status !== 'pending') {
            return response()->json(['message' => 'الطلب غير موجود في انتظار الموافقة'], 422);
        }

        DB::transaction(function () use ($order, $car) {
            $car->update(['is_available' => false]);
            $order->update(['order_status' => 'confirmed']);
            Payment::where('order_id', $order->id)
                   ->where('status', 'pending')
                   ->update(['status' => 'completed']);
        });

        $order->load(['car.images', 'user:id,name,phone']);
        return response()->json(['message' => 'تم قبول الطلب بنجاح', 'order' => $order]);
    }

    // ===================================================
    // صاحب السيارة: رفض الطلب
    // ===================================================
    public function reject(Request $request, Order $order)
    {
        $car = Car::findOrFail($order->car_id);

        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($order->order_status !== 'pending') {
            return response()->json(['message' => 'لا يمكن رفض هذا الطلب في حالته الحالية'], 422);
        }

        DB::transaction(function () use ($order, $car) {
            $car->update(['is_available' => true]);
            $order->update(['order_status' => 'rejected']);
            Payment::where('order_id', $order->id)
                   ->where('status', 'pending')
                   ->update(['status' => 'failed']);
        });

        return response()->json(['message' => 'تم رفض الطلب']);
    }

    // ===================================================
    // صاحب السيارة: تأكيد تسليم (بيع فقط)
    // ===================================================
    public function confirmReceive(Request $request, Order $order)
    {
        $car = Car::with('service')->findOrFail($order->car_id);

        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($order->order_status !== 'confirmed') {
            return response()->json(['message' => 'الطلب لازم يكون مقبولاً أولاً'], 422);
        }

        // ✅ تحقق بالـ service type بدل string matching
        if (!$car->isSale()) {
            return response()->json(['message' => 'هذا الإجراء للبيع فقط — للتأجير استخدم إنهاء التأجير'], 422);
        }

        DB::transaction(function () use ($car, $order) {
            $car->update(['status' => 'completed', 'is_available' => false]);
            $order->update(['order_status' => 'completed']);
        });

        return response()->json(['message' => 'تم تأكيد التسليم بنجاح — اكتملت صفقة البيع']);
    }

    // ===================================================
    // صاحب السيارة: تسليم السيارة للعميل (تأجير)
    // ===================================================
    public function markDelivered(Request $request, Order $order)
    {
        $car = Car::with('service')->findOrFail($order->car_id);

        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($order->order_status !== 'confirmed') {
            return response()->json(['message' => 'الطلب لازم يكون مقبولاً أولاً'], 422);
        }

        if (!$car->isRent()) {
            return response()->json(['message' => 'هذا الإجراء للتأجير فقط'], 422);
        }

        $order->update(['order_status' => 'in_progress']);
        return response()->json(['message' => 'تم تسجيل تسليم السيارة للعميل — في انتظار إعادتها']);
    }

    // ===================================================
    // صاحب السيارة: إنهاء التأجير
    // ===================================================
    public function complete(Request $request, Order $order)
    {
        $car = Car::with('service')->findOrFail($order->car_id);

        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($order->order_status !== 'in_progress') {
            return response()->json(['message' => 'السيارة لازم تكون مسلّمة للعميل أولاً'], 422);
        }

        if (!$car->isRent()) {
            return response()->json(['message' => 'في البيع، العميل هو من يؤكد الاستلام'], 422);
        }

        DB::transaction(function () use ($car, $order) {
            $car->update(['is_available' => true]);
            $order->update(['order_status' => 'completed']);
        });

        return response()->json(['message' => 'تم إنهاء التأجير بنجاح، السيارة متاحة مرة أخرى']);
    }

    // ===================================================
    // إلغاء بالتراضي — للعميل وصاحب السيارة
    // ===================================================
    public function cancelByAgreement(Request $request, Order $order)
    {
        $user       = $request->user();
        $isCustomer = $order->user_id === $user->id;
        $isCarOwner = Car::where('id', $order->car_id)->where('user_id', $user->id)->exists();

        if (!$isCustomer && !$isCarOwner) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($order->order_status === 'in_progress') {
            return response()->json(['message' => 'لا يمكن إلغاء الطلب — السيارة بحوزة العميل بالفعل'], 422);
        }

        if ($order->order_status !== 'confirmed') {
            return response()->json(['message' => 'لا يمكن الإلغاء في هذه الحالة'], 422);
        }

        DB::transaction(function () use ($order) {
            Car::where('id', $order->car_id)->update(['is_available' => true]);
            $order->update(['order_status' => 'canceled']);
            Payment::where('order_id', $order->id)
                   ->where('status', 'completed')
                   ->update(['status' => 'refunded']);
        });

        return response()->json(['message' => 'تم إلغاء الطلب بالتراضي، السيارة أصبحت متاحة مجدداً']);
    }

    // ===================================================
    // الأدمن: كل الطلبات
    // ===================================================
    public function index(Request $request)
    {
        $orders = Order::with(['user:id,name,phone', 'car.images', 'car.service', 'car.user:id,name', 'payments'])
            ->when($request->status, fn($q) => $q->where('order_status', $request->status))
            ->latest()
            ->paginate(15);

        return response()->json($orders);
    }
}

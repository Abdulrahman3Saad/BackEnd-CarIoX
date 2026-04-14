<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarSwap;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // ===================================================
    // إنشاء دفعة — يقبل order_id أو swap_id
    // ===================================================
    public function store(Request $request)
    {
        $request->validate([
            'order_id'           => 'nullable|exists:orders,id',
            'swap_id'            => 'nullable|exists:car_swaps,id',
            'car_id'             => 'required|exists:cars,id',
            'amount'             => 'required|numeric|min:1',
            'payment_type'       => 'required|in:service_fees,order_payment',
            'payment_method'     => 'required|in:cash,card,installment',
            'payment_provider'   => 'nullable|in:visa,mastercard,valu,nbe,cib,banquemisr',
            'total_installments' => 'nullable|integer|min:1',
            'installment_number' => 'nullable|integer|min:1',
        ]);

        if (!$request->order_id && !$request->swap_id) {
            return response()->json(['message' => 'يجب تحديد order_id أو swap_id'], 422);
        }

        $user = $request->user();
        $car  = Car::findOrFail($request->car_id);

        // ===================================================
        // Swap Payment
        // ===================================================
        if ($request->swap_id) {
            $swap = CarSwap::findOrFail($request->swap_id);

            if ($swap->requester_id !== $user->id && $swap->receiver_id !== $user->id) {
                return response()->json(['message' => 'غير مصرح'], 403);
            }

            if (!in_array($swap->status, ['admin_approved', 'accepted', 'completed'])) {
                return response()->json(['message' => 'الـ swap لازم يكون في مرحلة متقدمة'], 422);
            }

            $exists = Payment::where('swap_id', $request->swap_id)
                              ->where('user_id', $user->id)
                              ->where('payment_type', 'service_fees')
                              ->whereIn('status', ['pending', 'completed'])
                              ->exists();

            if ($exists) {
                return response()->json(['message' => 'تم تسجيل رسوم الخدمة لهذا التبديل مسبقاً'], 422);
            }

            $payment = Payment::create([
                'user_id'        => $user->id,
                'swap_id'        => $request->swap_id,
                'car_id'         => $request->car_id,
                'amount'         => $request->amount,
                'payment_type'   => $request->payment_type,
                'payment_method' => $request->payment_method ?? 'cash',
                'status'         => 'completed',
            ]);

            $payment->load(['swap', 'car']);
            return response()->json(['message' => 'تم تسجيل رسوم التبديل بنجاح', 'payment' => $payment], 201);
        }

        // ===================================================
        // Order Payment
        // ===================================================
        $order = Order::findOrFail($request->order_id);

        if ($request->payment_type === 'service_fees') {
            if ($car->user_id !== $user->id) {
                return response()->json(['message' => 'رسوم الخدمة تُحسب على صاحب الإعلان فقط'], 403);
            }
        } else {
            if ($order->user_id !== $user->id) {
                return response()->json(['message' => 'غير مصرح'], 403);
            }
        }

        if (!in_array($order->order_status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'لا يمكن تسجيل الدفع في هذه الحالة'], 422);
        }

        $exists = Payment::where('order_id', $request->order_id)
                         ->where('payment_type', $request->payment_type)
                         ->whereIn('status', ['pending', 'completed'])
                         ->exists();

        if ($exists) {
            return response()->json(['message' => 'تم تسجيل هذا النوع من الدفع لهذا الطلب مسبقاً'], 422);
        }

        $paymentStatus = $order->order_status === 'confirmed' ? 'completed' : 'pending';

        $payment = Payment::create([
            'user_id'            => $user->id,
            'order_id'           => $request->order_id,
            'car_id'             => $request->car_id,
            'amount'             => $request->amount,
            'payment_type'       => $request->payment_type,
            'payment_method'     => $request->payment_method,
            'payment_provider'   => $request->payment_provider,
            'total_installments' => $request->total_installments,
            'installment_number' => $request->installment_number,
            'status'             => $paymentStatus,
        ]);

        $payment->load(['order', 'car']);
        return response()->json(['message' => 'تم تسجيل الدفع بنجاح', 'payment' => $payment], 201);
    }

    // ===================================================
    // العميل: مدفوعاتي
    // ✅ فلتر بـ service name بدل type
    // ===================================================
    public function myPayments(Request $request)
    {
        $payments = Payment::with(['order', 'swap', 'car.images'])
            ->where('user_id', $request->user()->id)
            ->when($request->service_type, function ($q) use ($request) {
                $serviceType = $request->service_type;
                if ($serviceType === 'swap') {
                    $q->whereNotNull('swap_id');
                } else {
                    // ✅ فلتر بـ service name مباشرة
                    $q->whereNull('swap_id')
                      ->whereHas('car.service', fn($sq) =>
                          $sq->where('name', strtolower($serviceType))
                      );
                }
            })
            ->latest()
            ->paginate(15);

        return response()->json($payments);
    }

    // ===================================================
    // تفاصيل دفعة (العميل أو الأدمن)
    // ===================================================
    public function show(Request $request, Payment $payment)
    {
        if ($payment->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $payment->load(['order', 'swap', 'car.images', 'user:id,name,phone']);
        return response()->json($payment);
    }

    // ===================================================
    // الأدمن: كل المدفوعات مع فلاتر
    // ✅ فلتر بـ service name بدل type
    // ===================================================
    public function index(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 15), 100);

        $payments = Payment::with(['user:id,name,phone', 'order', 'swap', 'car.images', 'car.service', 'car.user:id,name'])
            ->when($request->status,       fn($q) => $q->where('status',         $request->status))
            ->when($request->method,       fn($q) => $q->where('payment_method', $request->method))
            ->when($request->payment_type, fn($q) => $q->where('payment_type',   $request->payment_type))
            ->when($request->service_type, function ($q) use ($request) {
                $type = $request->service_type;
                if ($type === 'swap') {
                    $q->whereNotNull('swap_id');
                } else {
                    // ✅ فلتر بـ service name مباشرة
                    $q->whereNull('swap_id')
                      ->whereHas('car.service', fn($sq) =>
                          $sq->where('name', strtolower($type))
                      );
                }
            })
            ->latest()
            ->paginate($perPage);

        return response()->json($payments);
    }

    // ===================================================
    // الأدمن: إحصائيات رسوم الخدمات
    // ✅ SQL aggregation بـ service name بدل type
    // ===================================================
    public function serviceFeesStats(Request $request)
    {
        // ✅ حساب رسوم كل خدمة بـ SQL مباشرة عن طريق service name
        $orderStats = Payment::selectRaw("
                SUM(amount) as total,
                SUM(CASE WHEN LOWER(s.name) = 'sale'     THEN amount ELSE 0 END) as sale_fees,
                SUM(CASE WHEN LOWER(s.name) = 'rent'     THEN amount ELSE 0 END) as rent_fees,
                SUM(CASE WHEN LOWER(s.name) = 'exchange' THEN amount ELSE 0 END) as swap_fees_from_orders
            ")
            ->join('cars', 'cars.id', '=', 'payments.car_id')
            ->join('services as s', 's.id', '=', 'cars.service_id')
            ->where('payments.payment_type', 'service_fees')
            ->where('payments.status', 'completed')
            ->whereNull('payments.swap_id')
            ->first();

        // رسوم الـ swap عن طريق swap_id
        $swapFees = Payment::where('payment_type', 'service_fees')
            ->where('status', 'completed')
            ->whereNotNull('swap_id')
            ->sum('amount');

        // ✅ عد السيارات النشطة بـ SQL عن طريق service name
        $carCounts = Car::selectRaw("
                SUM(CASE WHEN LOWER(s.name) = 'sale'     THEN 1 ELSE 0 END) as sale_count,
                SUM(CASE WHEN LOWER(s.name) = 'rent'     THEN 1 ELSE 0 END) as rent_count,
                SUM(CASE WHEN LOWER(s.name) = 'exchange' THEN 1 ELSE 0 END) as swap_count
            ")
            ->join('services as s', 's.id', '=', 'cars.service_id')
            ->where('cars.status', 'active')
            ->first();

        $totalSwapFees = floatval($orderStats->swap_fees_from_orders ?? 0) + floatval($swapFees);

        return response()->json([
            'success'            => true,
            'total_service_fees' => floatval($orderStats->total ?? 0) + floatval($swapFees),
            'sale_fees'          => floatval($orderStats->sale_fees  ?? 0),
            'sale_count'         => (int) ($carCounts->sale_count ?? 0),
            'rent_fees'          => floatval($orderStats->rent_fees  ?? 0),
            'rent_count'         => (int) ($carCounts->rent_count ?? 0),
            'swap_fees'          => $totalSwapFees,
            'swap_count'         => (int) ($carCounts->swap_count ?? 0),
        ]);
    }
}

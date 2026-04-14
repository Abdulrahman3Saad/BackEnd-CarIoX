<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarSwap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarSwapController extends Controller
{
    // ===================================================
    // إرسال طلب تبديل
    // ===================================================
    public function store(Request $request)
    {
        $request->validate([
            'my_car_id'             => 'required|exists:cars,id',
            'target_car_id'         => 'required|exists:cars,id|different:my_car_id',
            'requester_phone'       => 'required|string|max:20|regex:/^[0-9+\-\s]+$/',
            'requester_governorate' => 'required|string|max:100',
            'requester_address'     => 'required|string|max:255',
            'notes'                 => 'nullable|string|max:1000',
            'swap_date'             => 'nullable|date|after:today',
        ]);

        $user = $request->user();

        $myCar = Car::where('id', $request->my_car_id)
                    ->where('user_id', $user->id)
                    ->first();

        if (!$myCar) {
            return response()->json(['message' => 'هذه السيارة ليست ملكك'], 403);
        }

        if (in_array($myCar->status, ['rejected', 'completed'])) {
            return response()->json(['message' => 'سيارتك غير متاحة للتبديل حالياً'], 422);
        }

        return DB::transaction(function () use ($request, $user, $myCar) {
            // ✅ Lock السيارة المستهدفة لمنع race condition
            $targetCar = Car::lockForUpdate()
                ->where('id', $request->target_car_id)
                ->where('status', 'active')
                ->where('is_available', true)
                ->first();

            if (!$targetCar) {
                return response()->json(['message' => 'السيارة المطلوبة غير متاحة'], 404);
            }

            if ($targetCar->user_id === $user->id) {
                return response()->json(['message' => 'لا يمكنك طلب تبديل مع نفسك'], 422);
            }

            $exists = CarSwap::where('requester_car_id', $request->my_car_id)
                             ->where('receiver_car_id', $request->target_car_id)
                             ->where('status', 'pending')
                             ->exists();

            if ($exists) {
                return response()->json(['message' => 'لديك طلب تبديل معلق لهذه السيارة بالفعل'], 422);
            }

            $diff    = abs($myCar->price - $targetCar->price);
            $whoPays = 'none';
            if ($diff > 0) {
                $whoPays = $myCar->price < $targetCar->price ? 'requester' : 'receiver';
            }

            $swap = CarSwap::create([
                'requester_id'          => $user->id,
                'requester_car_id'      => $myCar->id,
                'receiver_id'           => $targetCar->user_id,
                'receiver_car_id'       => $targetCar->id,
                'requester_phone'       => $request->requester_phone,
                'requester_governorate' => $request->requester_governorate,
                'requester_address'     => $request->requester_address,
                'notes'                 => $request->notes,
                'price_difference'      => $diff,
                'who_pays_difference'   => $whoPays,
                'swap_date'             => $request->swap_date,
                'status'                => 'pending',
            ]);

            $myCar->update(['is_available' => false]);
            $targetCar->update(['is_available' => false]);

            $swap->load(['requester', 'receiver', 'requesterCar.images', 'receiverCar.images']);

            return response()->json([
                'message' => 'تم إرسال طلب التبديل، في انتظار مراجعة الإدارة أولاً',
                'swap'    => $swap,
            ], 201);
        });
    }

    // ===================================================
    // الأدمن: موافقة على طلب التبديل
    // ===================================================
    public function adminApprove(Request $request, CarSwap $carSwap)
    {
        if ($carSwap->status !== 'pending') {
            return response()->json(['message' => 'الطلب ليس في حالة انتظار'], 422);
        }

        $carSwap->update(['status' => 'admin_approved']);
        $carSwap->load(['requester', 'receiver', 'requesterCar.images', 'receiverCar.images']);

        return response()->json([
            'message' => 'تمت الموافقة الإدارية، في انتظار موافقة صاحب السيارة',
            'swap'    => $carSwap,
        ]);
    }

    // ===================================================
    // الأدمن: رفض طلب التبديل
    // ===================================================
    public function adminReject(Request $request, CarSwap $carSwap)
    {
        if ($carSwap->status !== 'pending') {
            return response()->json(['message' => 'الطلب ليس في حالة انتظار'], 422);
        }

        DB::transaction(function () use ($carSwap) {
            Car::whereIn('id', [$carSwap->requester_car_id, $carSwap->receiver_car_id])
               ->update(['is_available' => true]);
            $carSwap->update(['status' => 'rejected']);
        });

        return response()->json(['message' => 'تم رفض طلب التبديل من الإدارة']);
    }

    // ===================================================
    // الطلبات اللي أنا بعتها
    // ===================================================
    public function mySentSwaps(Request $request)
    {
        $swaps = CarSwap::with(['requesterCar.images', 'receiverCar.images', 'receiver:id,name,phone'])
            ->where('requester_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($swaps);
    }

    // ===================================================
    // الطلبات اللي جاية عليّ
    // ===================================================
    public function myReceivedSwaps(Request $request)
    {
        $swaps = CarSwap::with(['requesterCar.images', 'receiverCar.images', 'requester:id,name,phone'])
            ->where('receiver_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($swaps);
    }

    // ===================================================
    // تفاصيل طلب واحد
    // ===================================================
    public function show(Request $request, CarSwap $carSwap)
    {
        $user = $request->user();

        if ($carSwap->requester_id !== $user->id
            && $carSwap->receiver_id !== $user->id
            && $user->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $carSwap->load([
            'requester:id,name,phone',
            'receiver:id,name,phone',
            'requesterCar.images',
            'requesterCar.service',
            'receiverCar.images',
            'receiverCar.service',
        ]);

        return response()->json($carSwap);
    }

    // ===================================================
    // قبول طلب التبديل (صاحب السيارة) — بعد موافقة الأدمن
    // ===================================================
    public function accept(Request $request, CarSwap $carSwap)
    {
        if ($carSwap->receiver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($carSwap->status === 'pending') {
            return response()->json(['message' => 'الطلب لا يزال في انتظار موافقة الإدارة'], 422);
        }

        if ($carSwap->status !== 'admin_approved') {
            return response()->json(['message' => 'الطلب لا يمكن قبوله في حالته الحالية'], 422);
        }

        $carSwap->update(['status' => 'accepted']);
        $carSwap->load(['requester', 'receiver', 'requesterCar.images', 'receiverCar.images']);

        return response()->json([
            'message' => 'تم قبول طلب التبديل، في انتظار التسليم الفعلي',
            'swap'    => $carSwap,
        ]);
    }

    // ===================================================
    // رفض طلب التبديل (صاحب السيارة)
    // ===================================================
    public function reject(Request $request, CarSwap $carSwap)
    {
        if ($carSwap->receiver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($carSwap->status !== 'admin_approved') {
            return response()->json(['message' => 'الطلب لا يمكن رفضه في حالته الحالية'], 422);
        }

        DB::transaction(function () use ($carSwap) {
            Car::whereIn('id', [$carSwap->requester_car_id, $carSwap->receiver_car_id])
               ->update(['is_available' => true]);
            $carSwap->update(['status' => 'rejected']);
        });

        return response()->json(['message' => 'تم رفض طلب التبديل، السيارتان أصبحتا متاحتين مجدداً']);
    }

    // ===================================================
    // إلغاء طلب التبديل (صاحب الطلب)
    // ===================================================
    public function cancel(Request $request, CarSwap $carSwap)
    {
        if ($carSwap->requester_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if (!in_array($carSwap->status, ['pending', 'admin_approved', 'accepted'])) {
            return response()->json(['message' => 'لا يمكن إلغاء الطلب في هذه الحالة'], 422);
        }

        DB::transaction(function () use ($carSwap) {
            Car::whereIn('id', [$carSwap->requester_car_id, $carSwap->receiver_car_id])
               ->update(['is_available' => true]);
            $carSwap->update(['status' => 'canceled']);
        });

        return response()->json(['message' => 'تم إلغاء طلب التبديل، السيارتان أصبحتا متاحتين مجدداً']);
    }

    // ===================================================
    // إتمام التبديل — عند التسليم الفعلي
    // ===================================================
    public function complete(Request $request, CarSwap $carSwap)
    {
        if ($carSwap->receiver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح — تأكيد التسليم لصاحب السيارة فقط'], 403);
        }

        if ($carSwap->status !== 'accepted') {
            return response()->json(['message' => 'الطلب لازم يكون مقبولاً أولاً'], 422);
        }

        DB::transaction(function () use ($carSwap) {
            // ✅ تبادل الملكية
            Car::where('id', $carSwap->requester_car_id)->update([
                'user_id'      => $carSwap->receiver_id,
                'status'       => 'completed',
                'is_available' => false,
            ]);

            Car::where('id', $carSwap->receiver_car_id)->update([
                'user_id'      => $carSwap->requester_id,
                'status'       => 'completed',
                'is_available' => false,
            ]);

            $carSwap->update(['status' => 'completed']);
        });

        $carSwap->load(['requester', 'receiver', 'requesterCar.images', 'receiverCar.images']);

        return response()->json([
            'message' => 'تم تأكيد التسليم وإتمام التبديل بنجاح!',
            'swap'    => $carSwap,
        ]);
    }

    // ===================================================
    // الأدمن: كل الطلبات
    // ===================================================
    public function index(Request $request)
    {
        $swaps = CarSwap::with(['requester:id,name,phone', 'receiver:id,name,phone', 'requesterCar', 'receiverCar'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15);

        return response()->json($swaps);
    }
}

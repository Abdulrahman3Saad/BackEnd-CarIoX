<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarImage;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarController extends Controller
{
    // ===================================================
    // عرض كل السيارات النشطة (للعموم) — مع فلاتر
    // ===================================================
    public function index(Request $request)
    {
        $query = Car::with(['user:id,name,phone', 'service', 'images'])
            ->where('status', 'active')
            ->where('is_available', true);

        if ($request->service_id) {
            $query->where('service_id', $request->service_id);
        }

        // ✅ فلتر بالـ service type (sale/rent/swap) بدل string matching
        if ($request->service_type) {
            $query->whereHas('service', fn($q) => $q->where('type', $request->service_type));
        }

        if ($request->brand) {
            $query->where('brand', 'like', '%' . $request->brand . '%');
        }

        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->city) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->car_condition) {
            $query->where('car_condition', $request->car_condition);
        }

        if ($request->fuel_type) {
            $query->where('fuel_type', $request->fuel_type);
        }

        if ($request->manufacture_year) {
            $query->where('manufacture_year', $request->manufacture_year);
        }

        $cars = $query->latest()->paginate(12);
        return response()->json($cars);
    }

    // ===================================================
    // تفاصيل سيارة واحدة
    // ===================================================
    public function show(Car $car)
    {
        $car->load(['user:id,name,phone', 'service', 'images']);
        return response()->json($car);
    }

    // ===================================================
    // صاحب السيارة: إضافة إعلان
    // ===================================================
    public function store(Request $request)
    {
        $request->validate([
            'service_id'       => 'required|exists:services,id',
            'brand'            => 'required|string|max:100',
            'model'            => 'required|string|max:100',
            'manufacture_year' => 'required|integer|min:1990|max:' . (date('Y') + 1),
            'car_condition'    => 'required|in:new,used,old',
            'fuel_type'        => 'required|in:petrol,electric,diesel,hybrid',
            'transmission'     => 'required|in:automatic,manual',
            'mileage'          => 'required|integer|min:0',
            'seats'            => 'required|integer|min:2|max:12',
            'color'            => 'required|string|max:50',
            'price'            => 'required|numeric|min:1',
            'description'      => 'nullable|string|max:2000',
            'city'             => 'required|string|max:100',
            'address'          => 'required|string|max:255',
            'contact_phone'    => 'required|string|max:20|regex:/^[0-9+\-\s]+$/',
            'images'           => 'required|array|min:1|max:10',
            'images.*'         => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // ✅ Car number فريد وآمن — لا يتكرر حتى مع requests متزامنة
        $carNumber = 'CAR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));

        $car = Car::create([
            'user_id'          => $request->user()->id,
            'service_id'       => $request->service_id,
            'car_number'       => $carNumber,
            'brand'            => $request->brand,
            'model'            => $request->model,
            'manufacture_year' => $request->manufacture_year,
            'car_condition'    => $request->car_condition,
            'fuel_type'        => $request->fuel_type,
            'transmission'     => $request->transmission,
            'mileage'          => $request->mileage,
            'seats'            => $request->seats,
            'color'            => $request->color,
            'price'            => $request->price,
            'description'      => $request->description,
            'status'           => 'pending',
            'is_available'     => false,
            'city'             => $request->city,
            'address'          => $request->address,
            'contact_phone'    => $request->contact_phone,
        ]);

        // ✅ تخزين الصور — image_url تُحسب ديناميكياً من الـ Accessor
        foreach ($request->file('images') as $image) {
            $path = $image->store('cars', 'public');
            CarImage::create([
                'car_id'     => $car->id,
                'image_path' => $path,
                'image_url'  => '', // الـ accessor هو اللي بيحسبها
            ]);
        }

        $car->load(['service', 'images']);
        return response()->json([
            'message' => 'تم رفع الإعلان بنجاح، في انتظار موافقة الإدارة',
            'car'     => $car,
        ], 201);
    }

    // ===================================================
    // صاحب السيارة: تعديل إعلان
    // ===================================================
    public function update(Request $request, Car $car)
    {
        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($car->status === 'completed') {
            return response()->json(['message' => 'لا يمكن تعديل سيارة تم إتمام صفقتها'], 422);
        }

        $request->validate([
            'service_id'       => 'sometimes|exists:services,id',
            'brand'            => 'sometimes|string|max:100',
            'model'            => 'sometimes|string|max:100',
            'manufacture_year' => 'sometimes|integer|min:1990|max:' . (date('Y') + 1),
            'car_condition'    => 'sometimes|in:new,used,old',
            'fuel_type'        => 'sometimes|in:petrol,electric,diesel,hybrid',
            'transmission'     => 'sometimes|in:automatic,manual',
            'mileage'          => 'sometimes|integer|min:0',
            'seats'            => 'sometimes|integer|min:2|max:12',
            'color'            => 'sometimes|string|max:50',
            'price'            => 'sometimes|numeric|min:1',
            'description'      => 'nullable|string|max:2000',
            'city'             => 'sometimes|string|max:100',
            'address'          => 'nullable|string|max:255',
            'contact_phone'    => 'sometimes|string|max:20|regex:/^[0-9+\-\s]+$/',
            'images'           => 'sometimes|array|min:1|max:10',
            'images.*'         => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $updateData = $request->only([
            'service_id', 'brand', 'model', 'manufacture_year', 'car_condition',
            'fuel_type', 'transmission', 'mileage', 'seats',
            'color', 'price', 'description', 'city', 'address', 'contact_phone',
        ]);

        // لو كانت active وتم تعديلها → ترجع pending للمراجعة
        if ($car->status === 'active') {
            $updateData['status']       = 'pending';
            $updateData['is_available'] = false;
        }

        $car->update($updateData);

        if ($request->hasFile('images')) {
            foreach ($car->images as $img) {
                Storage::disk('public')->delete($img->image_path);
                $img->delete();
            }
            foreach ($request->file('images') as $image) {
                $path = $image->store('cars', 'public');
                CarImage::create([
                    'car_id'     => $car->id,
                    'image_path' => $path,
                    'image_url'  => '',
                ]);
            }
        }

        $car->load(['service', 'images']);
        return response()->json([
            'message' => 'تم تحديث الإعلان بنجاح',
            'car'     => $car,
        ]);
    }

    // ===================================================
    // صاحب السيارة أو الأدمن: حذف إعلان (Soft Delete)
    // ===================================================
    public function destroy(Request $request, Car $car)
    {
        $user = $request->user();

        if ($car->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // حذف الصور من الـ storage أولاً
        foreach ($car->images as $img) {
            Storage::disk('public')->delete($img->image_path);
        }

        // ✅ Soft Delete — البيانات تبقى في الداتابيز
        $car->delete();

        return response()->json(['message' => 'تم حذف الإعلان بنجاح']);
    }

    // ===================================================
    // صاحب السيارة: سياراتي (مع pagination)
    // ===================================================
    public function myCars(Request $request)
    {
        $cars = Car::with(['service', 'images'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($cars);
    }

    // ===================================================
    // الأدمن: الإعلانات في انتظار الموافقة
    // ===================================================
    public function pendingCars(Request $request)
    {
        // ✅ الحماية تأتي من AdminMiddleware في الـ routes
        $cars = Car::with(['user:id,name,email,phone', 'service', 'images'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return response()->json($cars);
    }

    // ===================================================
    // الأدمن: قبول إعلان
    // ===================================================
    public function approveCar(Request $request, Car $car)
    {
        if ($car->status !== 'pending') {
            return response()->json(['message' => 'الإعلان غير موجود في انتظار الموافقة'], 422);
        }

        $car->load('service');

        $car->update([
            'status'       => 'active',
            'is_available' => true,
        ]);

        // ✅ تسجيل رسوم نشر الإعلان تلقائياً
        if ($car->service && $car->service->service_fees > 0) {
            Payment::create([
                'user_id'        => $car->user_id,
                'car_id'         => $car->id,
                'order_id'       => null,
                'amount'         => $car->service->service_fees,
                'payment_type'   => 'service_fees',
                'payment_method' => 'cash',
                'status'         => 'completed',
            ]);
        }

        return response()->json([
            'message' => 'تم قبول الإعلان ونشره بنجاح، وتم تسجيل رسوم الخدمة',
            'car'     => $car,
        ]);
    }

    // ===================================================
    // الأدمن: رفض إعلان
    // ===================================================
    public function rejectCar(Request $request, Car $car)
    {
        if ($car->status !== 'pending') {
            return response()->json(['message' => 'الإعلان غير موجود في انتظار الموافقة'], 422);
        }

        $car->update(['status' => 'rejected']);

        return response()->json(['message' => 'تم رفض الإعلان']);
    }
}

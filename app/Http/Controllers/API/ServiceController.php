<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    // عرض كل الخدمات — ✅ مع Cache
    public function index()
    {
        return response()->json(Service::allCached());
    }

    // ✅ إضافة خدمة (الأدمن فقط — محمية بـ AdminMiddleware في الـ routes)
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|unique:services|max:100',
            'service_fees' => 'required|numeric|min:0',
            'description'  => 'nullable|string|max:500',
        ]);

        $service = Service::create($request->only(['name', 'service_fees', 'description']));
        return response()->json($service, 201);
    }

    // ✅ تعديل خدمة (الأدمن فقط)
    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name'         => 'sometimes|string|unique:services,name,' . $service->id . '|max:100',
            'service_fees' => 'sometimes|numeric|min:0',
            'description'  => 'nullable|string|max:500',
        ]);

        $service->update($request->only(['name', 'service_fees', 'description']));
        return response()->json($service);
    }

    // ✅ حذف خدمة (الأدمن فقط)
    public function destroy(Service $service)
    {
        if ($service->cars()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف خدمة مرتبطة بسيارات'], 422);
        }

        $service->delete();
        return response()->json(['message' => 'تم حذف الخدمة بنجاح']);
    }
}

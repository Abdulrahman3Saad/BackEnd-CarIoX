<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users',
            'password'        => 'required|string|min:8|confirmed',
            'phone'           => 'required|string|max:20|regex:/^[0-9+\-\s]+$/',
            'job_title'       => 'nullable|string|max:100',
            'marital_status'  => 'nullable|in:single,married,divorced,widowed',
            'bio'             => 'nullable|string|max:1000',
            'annual_income'   => 'nullable|numeric|min:0',
            'education_level' => 'nullable|string|max:100',
        ]);

        $user = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'phone'           => $request->phone,
            'job_title'       => $request->job_title,
            'marital_status'  => $request->marital_status,
            'bio'             => $request->bio,
            'annual_income'   => $request->annual_income,
            'education_level' => $request->education_level,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
            ], 401);
        }

        $user  = Auth::user();

        // ✅ حذف الـ tokens القديمة (اختياري — يمنع تراكم tokens)
        // $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'             => 'sometimes|string|max:255',
            'phone'            => 'sometimes|string|max:20|regex:/^[0-9+\-\s]+$/',
            'job_title'        => 'nullable|string|max:100',
            'marital_status'   => 'nullable|in:single,married,divorced,widowed',
            'bio'              => 'nullable|string|max:1000',
            'annual_income'    => 'nullable|numeric|min:0',
            'education_level'  => 'nullable|string|max:100',
            'current_password' => 'required_with:password|string',
            'password'         => 'sometimes|string|min:8|confirmed',
        ]);

        $data = $request->only([
            'name', 'phone', 'job_title', 'marital_status',
            'bio', 'annual_income', 'education_level',
        ]);

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة'], 422);
            }
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'تم تحديث البيانات بنجاح',
            'user'    => $user->fresh(),
        ]);
    }
}

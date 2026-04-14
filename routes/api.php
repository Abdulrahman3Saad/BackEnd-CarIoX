<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\CarController;
use App\Http\Controllers\API\CarSwapController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PaymentController;
use Illuminate\Support\Facades\Route;

// ===================================================
// Public Routes — بدون تسجيل دخول
// ✅ Rate Limiting على الـ Auth لمنع Brute Force
// ===================================================
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

Route::get('/services',   [ServiceController::class, 'index']);
Route::get('/cars',       [CarController::class, 'index']);
Route::get('/cars/{car}', [CarController::class, 'show']);

// ===================================================
// Protected Routes — بعد تسجيل الدخول
// ===================================================
Route::middleware('auth:sanctum')->group(function () {

    // ---- Auth ----
    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::get('/profile',  [AuthController::class, 'profile']);
    Route::put('/profile',  [AuthController::class, 'updateProfile']);

    // ================================================
    // Cars — صاحب السيارة
    // ================================================
    Route::post('/cars',         [CarController::class, 'store']);
    Route::post('/cars/{car}',   [CarController::class, 'update']);
    Route::delete('/cars/{car}', [CarController::class, 'destroy']);
    Route::get('/my-cars',       [CarController::class, 'myCars']);

    // ================================================
    // Cars — الأدمن فقط
    // ✅ AdminMiddleware يمنع أي user عادي من الوصول
    // ================================================
    Route::middleware('admin')->group(function () {
        Route::get('/admin/cars/pending',        [CarController::class, 'pendingCars']);
        Route::post('/admin/cars/{car}/approve', [CarController::class, 'approveCar']);
        Route::post('/admin/cars/{car}/reject',  [CarController::class, 'rejectCar']);
    });

    // ================================================
    // Services — الأدمن فقط
    // ✅ AdminMiddleware — لا يحتاج if check في الـ controller
    // ================================================
    Route::middleware('admin')->group(function () {
        Route::post('/services',             [ServiceController::class, 'store']);
        Route::put('/services/{service}',    [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    });

    // ================================================
    // Orders
    // ================================================
    Route::post('/orders',                [OrderController::class, 'store']);
    Route::get('/my-orders',              [OrderController::class, 'myOrders']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::get('/incoming-orders',                     [OrderController::class, 'incomingOrders']);
    Route::post('/orders/{order}/approve',             [OrderController::class, 'approve']);
    Route::post('/orders/{order}/reject',              [OrderController::class, 'reject']);
    Route::post('/orders/{order}/complete',            [OrderController::class, 'complete']);
    Route::post('/orders/{order}/confirm-receive',     [OrderController::class, 'confirmReceive']);
    Route::post('/orders/{order}/mark-delivered',      [OrderController::class, 'markDelivered']);
    Route::post('/orders/{order}/cancel-by-agreement', [OrderController::class, 'cancelByAgreement']);
    Route::get('/orders/{order}',                      [OrderController::class, 'show']);

    // الأدمن فقط
    Route::middleware('admin')->group(function () {
        Route::get('/admin/orders', [OrderController::class, 'index']);
    });

    // ================================================
    // Payments
    // ================================================
    Route::post('/payments',          [PaymentController::class, 'store']);
    Route::get('/my-payments',        [PaymentController::class, 'myPayments']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);

    // الأدمن فقط
    Route::middleware('admin')->group(function () {
        // ⚠️ service-fees-stats قبل {payment} عشان Laravel ميعتبرهاش ID
        Route::get('/admin/payments/service-fees-stats', [PaymentController::class, 'serviceFeesStats']);
        Route::get('/admin/payments',                    [PaymentController::class, 'index']);
    });

    // ================================================
    // Car Swaps — User Routes
    // ================================================
    Route::prefix('swaps')->group(function () {
        Route::post('/',                   [CarSwapController::class, 'store']);
        Route::get('/sent',                [CarSwapController::class, 'mySentSwaps']);
        Route::get('/received',            [CarSwapController::class, 'myReceivedSwaps']);
        Route::get('/{carSwap}',           [CarSwapController::class, 'show']);
        Route::post('/{carSwap}/accept',   [CarSwapController::class, 'accept']);
        Route::post('/{carSwap}/reject',   [CarSwapController::class, 'reject']);
        Route::post('/{carSwap}/cancel',   [CarSwapController::class, 'cancel']);
        Route::post('/{carSwap}/complete', [CarSwapController::class, 'complete']);
    });

    // Car Swaps — الأدمن فقط
    Route::middleware('admin')->group(function () {
        Route::get('/admin/swaps',                    [CarSwapController::class, 'index']);
        Route::post('/admin/swaps/{carSwap}/approve', [CarSwapController::class, 'adminApprove']);
        Route::post('/admin/swaps/{carSwap}/reject',  [CarSwapController::class, 'adminReject']);
    });

});

<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ✅ تسجيل AdminMiddleware كـ alias
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // ✅ 1) Unauthenticated → 401 JSON دايماً
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح. يرجى تسجيل الدخول أولاً.',
            ], 401);
        });

        // ✅ 2) Model/Route Not Found → 404 JSON
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'المورد المطلوب غير موجود.',
            ], 404);
        });

        // ✅ 3) Method Not Allowed → 405 JSON
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'الـ HTTP method غير مسموح على هذا الـ endpoint.',
            ], 405);
        });

    })->create();
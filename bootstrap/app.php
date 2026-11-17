<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Đặt biệt danh 'admin' cho middleware, để routes/web.php gọi ->middleware('admin')
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
        $middleware->web(append: [EnsureUserIsActive::class, SecurityHeaders::class]);
        // Kiểm quyền admin TRƯỚC khi nạp model từ URL: người không có quyền luôn nhận 403,
        // không phân biệt id có tồn tại hay không (tránh dò số đơn / phiếu bằng 404 vs 403).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: AdminMiddleware::class);
        // Tài khoản bị khóa -> đăng xuất NGAY, trước cả kiểm quyền admin
        $middleware->prependToPriorityList(before: AdminMiddleware::class, prepend: EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

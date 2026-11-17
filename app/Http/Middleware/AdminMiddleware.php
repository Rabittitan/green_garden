<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Nếu chưa đăng nhập hoặc không phải admin thì báo lỗi 403 (cấm truy cập)
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            abort(403, 'Bạn không có quyền truy cập trang quản trị.');
        }
        // Là admin thì cho đi tiếp
        return $next($request);
    }
}
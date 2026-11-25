<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Khu quản trị.  ->middleware('admin')          : nhân viên + quản trị
 *                ->middleware('admin:orders')   : quản trị, hoặc nhân viên được cấp quyền đó (App\Enums\StaffPermission)
 *                ->middleware('admin:admin')    : CHỈ quản trị (tiền, chính sách, nhân viên)
 * Chạy TRƯỚC khi nạp model từ URL (bootstrap/app.php): không có quyền luôn nhận 403, kể cả với id không tồn tại.
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next, string $level = 'staff'): Response
    {
        $user = $request->user();
        $ok = $user && $user->is_active && match ($level) {
            'admin' => $user->isAdmin(),
            'staff' => $user->isBackOffice(),
            default => $user->isBackOffice() && $user->hasPermission($level),
        };
        if (! $ok) {
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        return $next($request);
    }
}

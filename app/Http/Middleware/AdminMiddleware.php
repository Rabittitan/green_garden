<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Khu quản trị.  ->middleware('admin')        : nhân viên + quản trị (xử lý đơn, kho)
 *                ->middleware('admin:admin')  : CHỈ quản trị (mã giảm giá, hạng, hoàn tiền, đổi hạng tay)
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next, string $level = 'staff'): Response
    {
        $user = $request->user();
        $ok = $user && $user->is_active && ($level === 'admin' ? $user->role === UserRole::Admin : $user->isBackOffice());
        if (! $ok) {
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        return $next($request);
    }
}

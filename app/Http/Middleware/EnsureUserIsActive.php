<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Tài khoản bị khóa trong lúc đang đăng nhập: đăng xuất ngay ở request kế tiếp. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): RedirectResponse|Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ shop.']);
        }

        return $next($request);
    }
}

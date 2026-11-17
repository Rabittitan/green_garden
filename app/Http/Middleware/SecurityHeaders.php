<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header bảo mật cho mọi trang web.
 *
 * CSP: chỉ nạp script / style từ chính site + CDN đang dùng (jsdelivr, cdnjs); không cho nhúng site vào iframe
 * (chống clickjacking); form chỉ được gửi về chính site hoặc cổng VNPay (Chrome áp form-action cho cả chuyển hướng
 * sau khi gửi form: thiếu host VNPay là thanh toán online bị chặn).
 * 'unsafe-inline' còn cần cho script/style vì view đang dùng onsubmit / onerror / style nội tuyến; mọi dữ liệu người
 * dùng đều đã được Blade escape nên đây là lớp phòng thủ thứ hai, không phải lớp duy nhất.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $vnpay = parse_url((string) config('shop.vnpay.url'), PHP_URL_HOST);
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "font-src 'self' data: https://cdnjs.cloudflare.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'".($vnpay ? " https://{$vnpay}" : ''),
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

/**
 * Xác thực email.
 * Luồng: đăng ký -> Laravel gửi mail có link ký số (signed URL, hết hạn sau 60 phút)
 *        -> khách bấm link -> verify() đánh dấu email_verified_at -> được phép đặt hàng.
 */
class EmailVerificationController extends Controller
{
    // Trang "Vui lòng xác thực email" (middleware 'verified' tự chuyển khách chưa xác thực về đây)
    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');

        }
        return view('auth.verify-email'); // auth/verify-email.blade.php
    }

    // Khách bấm link trong mail. EmailVerificationRequest tự kiểm tra id + hash có khớp user đang đăng nhập không
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();   // set email_verified_at = now() và phát event Verified

        return redirect()->intended(route('home'))
            ->with('success', 'Xác thực email thành công! Bây giờ bạn có thể đặt hàng.');
    }

    // Gửi lại mail xác thực
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Đã gửi lại email xác thực tới '.$request->user()->email.'.');
    }
}
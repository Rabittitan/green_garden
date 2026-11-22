<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\Account\PasswordChanged;
use App\Support\Notifier;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Quên mật khẩu: nhập email -> mail chứa link (dùng 1 lần, hết hạn sau auth.passwords.users.expire phút)
 * -> đặt mật khẩu mới. Dùng cơ chế Password broker có sẵn của Laravel (bảng password_reset_tokens).
 *
 * Chống dò tài khoản: email không tồn tại / tài khoản bị khóa / vừa gửi xong đều nhận CÙNG một thông báo;
 * link sai / hết hạn / sai email khi đặt lại cũng cùng một thông báo.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        try {
            // is_active: tài khoản bị khóa không nhận được link (vẫn trả cùng thông báo bên dưới)
            Password::sendResetLink(['email' => trim($data['email']), 'is_active' => true]);
        } catch (\Throwable $e) {
            report($e);                                       // lỗi SMTP: không lộ ra là email có tồn tại
        }

        return back()->with('success', __('passwords.sent'));
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            ['email' => trim($data['email']), 'is_active' => true, 'token' => $data['token'],
                'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation')],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                // Đăng xuất mọi phiên đang mở của tài khoản (kẻ đã chiếm phiên cũng bị đẩy ra)
                if (config('session.driver') === 'database') {
                    DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
                event(new PasswordReset($user));
                Notifier::send($user, new PasswordChanged(now(), viaReset: true));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Sai token / hết hạn / sai email / tài khoản bị khóa: một thông báo chung
            return back()->withInput($request->only('email'))->withErrors(['email' => __('passwords.token')]);
        }

        return redirect()->route('login')->with('success', __('passwords.reset').' Vui lòng đăng nhập bằng mật khẩu mới.');
    }
}

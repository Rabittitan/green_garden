<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // Hiện form đăng nhập
    public function showLogin(): View
    {
        return view('auth.login');
    }

    // Xử lý đăng nhập
    public function login(Request $request): RedirectResponse
    {
        // Lấy email và mật khẩu, bắt buộc phải nhập
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Auth::attempt tự so sánh email + mật khẩu (đã mã hóa) với bảng users
        // is_active = false: tài khoản bị khóa thì không đăng nhập được
        if (Auth::attempt($credentials + ['is_active' => true])) {
            $request->session()->regenerate();                 // đổi session id để chống tấn công session fixation

            if (Auth::user()->isBackOffice()) {                // nhân viên / quản trị
                return redirect()->route('admin.dashboard');
            }

            return redirect()->intended(route('home'));        // khách: quay lại trang đang xem trước khi đăng nhập
        }

        // Sai thông tin thì quay lại form, giữ lại email đã nhập
        return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng!'])->withInput();
    }

    // Hiện form đăng ký
    public function showRegister(): View
    {
        return view('auth.register');
    }

    // Xử lý đăng ký
    public function register(Request $request): RedirectResponse
    {
        // Kiểm tra dữ liệu: email không được trùng, mật khẩu >= 6 ký tự và phải nhập lại khớp (password_confirmation)
        $data = $request->validate([
            'name' => 'required|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'regex:/^(0|\+84)[0-9]{9,10}$/'],
        ], [
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0901234567).',
        ]);

        // role / hạng không nằm trong $fillable: tài khoản mới luôn là khách, hạng cơ bản (Model tự gán + ghi lịch sử)
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),       // mã hóa mật khẩu, KHÔNG lưu mật khẩu thô
            'phone' => $data['phone'] ?? null,
        ]);

        try {
            event(new Registered($user));                      // Laravel tự gửi mail xác thực (vì User implements MustVerifyEmail)
        } catch (\Throwable $e) {
            report($e);                                        // lỗi SMTP không được làm hỏng việc đăng ký; khách bấm "Gửi lại" sau
        }

        Auth::login($user);                                    // đăng nhập luôn cho tiện
        $request->session()->regenerate();                     // đổi session id sau khi đăng nhập

        return redirect()->route('verification.notice')
            ->with('success', 'Đăng ký thành công! Vui lòng kiểm tra email để xác thực tài khoản.');
    }

    // Đăng xuất
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();                                        // xóa trạng thái đăng nhập
        $request->session()->invalidate();                     // hủy session cũ (giỏ hàng cũng mất)
        $request->session()->regenerateToken();                // tạo CSRF token mới

        return redirect()->route('home');                      // về trang chủ
    }
}

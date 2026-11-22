<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Notifications\Account\EmailChanged;
use App\Notifications\Account\PasswordChanged;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Trang hồ sơ: xem/sửa thông tin cá nhân và đổi mật khẩu.
 */
class ProfileController extends Controller
{
    // Hiện trang hồ sơ
    public function edit(Request $request): View
    {
        $user = $request->user();

        // Thống kê nhỏ cho cột bên trái
        $user->load('tier');
        $stats = [
            'orders' => $user->orders()->count(),
            'completed' => $user->orders()->where('status', OrderStatus::Completed->value)->count(),
            'spent' => $user->lifetime_spend,                       // tổng chi tiêu đã chốt (dùng xét hạng)
        ];
        $latestOrders = $user->orders()->latest('id')->take(5)->get();

        return view('profile.edit', compact('user', 'stats', 'latestOrders'));
    }

    // Cập nhật thông tin cá nhân
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^(0|\+84)[0-9]{9,10}$/'],
        ], [
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0901234567).',
        ]);

        $oldEmail = $user->email;
        $emailChanged = $data['email'] !== $oldEmail;

        $user->fill($data);
        if ($emailChanged) {
            $user->email_verified_at = null;              // email mới thì phải xác thực lại
        }
        $user->save();

        if ($emailChanged) {
            Notifier::sendToAddress($oldEmail, new EmailChanged($user->name, $user->email, now()));   // báo chủ cũ: chống chiếm tài khoản
            $user->sendEmailVerificationNotification();   // gửi link xác thực tới email mới

            return redirect()->route('verification.notice')
                ->with('success', 'Đã cập nhật email. Vui lòng kiểm tra hộp thư để xác thực email mới.');
        }

        return back()->with('success', 'Đã cập nhật thông tin cá nhân.');
    }

    // Đổi mật khẩu
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validateWithBag('password', [
            'current_password' => 'required|current_password',   // phải nhập đúng mật khẩu hiện tại
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
        ]);

        $request->user()->update(['password' => Hash::make($request->password)]);
        Notifier::send($request->user(), new PasswordChanged(now()));
        $request->session()->regenerate();                         // đổi session id sau khi đổi mật khẩu

        return back()->with('success', 'Đổi mật khẩu thành công.');
    }
}

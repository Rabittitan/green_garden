<?php

namespace App\Support;

use App\Enums\StaffPermission;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Lỗi 403 (không có quyền / link ký sai) trên TRANG WEB: thay vì trang lỗi, đưa người dùng về trang đầu của vai trò
 * kèm thông báo nổi (yêu cầu chủ shop 26/11):
 *  - nhân viên / quản trị -> trang đầu khu quản trị (Thống kê, hoặc mục đầu tiên nhân viên được cấp quyền);
 *  - khách / chưa đăng nhập -> trang chủ shop.
 * Giữ nguyên 403 khi: gọi JSON (máy quét, AJAX), hoặc route không có phiên (VD /storage/...).
 * Kiểm quyền vẫn chạy trước khi nạp model từ URL: id có hay không đều nhận cùng một phản hồi (không dò được id).
 */
final class NoAccessRedirect
{
    public const DENIED = 'Bạn không có quyền truy cập trang đó.';

    public static function for(HttpExceptionInterface $e, Request $request): ?RedirectResponse
    {
        if ($e->getStatusCode() !== 403 || $request->expectsJson() || ! $request->hasSession()) {
            return null;
        }
        $user = $request->user();
        $home = $user instanceof User && $user->isBackOffice() ? route(self::adminLanding($user)) : route('home');
        if ($home === $request->url()) {                                     // phòng vòng lặp: chính trang đầu cũng bị chặn
            $home = route('home');
        }
        $message = $e instanceof InvalidSignatureException
            ? 'Liên kết không hợp lệ hoặc đã hết hạn.'
            : self::DENIED;

        return redirect()->to($home)->with('error', $message);
    }

    /** Trang đầu khu quản trị của nhân viên: Thống kê nếu có quyền, không thì mục đầu tiên được cấp (chuyển 1 lần, giữ được thông báo). */
    public static function adminLanding(User $user): string
    {
        if ($user->hasPermission(StaffPermission::Reports)) {
            return 'admin.dashboard';
        }
        foreach (DashboardController::LANDING as $perm => $route) {
            if ($user->hasPermission($perm)) {
                return $route;
            }
        }

        return 'admin.dashboard';                                            // trang "chưa được cấp quyền dùng mục nào"
    }
}

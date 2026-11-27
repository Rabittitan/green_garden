<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Quản trị viên quản lý tài khoản khách (khóa / mở khóa). Tài khoản nhân viên / quản trị quản lý ở StaffService. */
class CustomerService
{
    /** Khóa / mở khóa. Bị khóa thì lần bấm kế tiếp bị đăng xuất ngay (EnsureUserIsActive). */
    public function toggleActive(User $customer): User
    {
        if ($customer->isBackOffice()) {
            throw new BusinessException('Tài khoản nhân viên / quản trị được khóa ở trang Nhân viên.');
        }

        return DB::transaction(function () use ($customer) {
            $locked = User::whereKey($customer->id)->lockForUpdate()->firstOrFail();   // đọc lại sau khi khóa: bấm đúp không đảo 2 lần sai
            $locked->forceFill(['is_active' => ! $locked->is_active])->save();

            return $locked;
        }, 3);
    }
}

<?php

namespace App\Services;

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Quản trị viên quản lý tài khoản nhân viên: tạo, cấp nhóm quyền, khóa / mở, đặt lại mật khẩu. */
class StaffService
{
    /** @param list<string> $permissions */
    public function create(string $name, string $email, string $password, array $permissions): User
    {
        return DB::transaction(function () use ($name, $email, $password, $permissions) {
            // Tạo qua User::create để model tự gán hạng cơ bản + lịch sử hạng (bất biến của mọi tài khoản)
            $user = User::create(['name' => $name, 'email' => mb_strtolower(trim($email)), 'password' => Hash::make($password)]);
            $user->forceFill(['role' => UserRole::Staff, 'is_active' => true, 'permissions' => $this->clean($permissions),
                'email_verified_at' => now()])->save();

            return $user;
        }, 3);
    }

    /** @param list<string> $permissions */
    public function update(User $staff, string $name, array $permissions, bool $active, ?string $newPassword): User
    {
        return DB::transaction(function () use ($staff, $name, $permissions, $active, $newPassword) {
            $staff = User::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            if ($staff->role !== UserRole::Staff) {
                throw new BusinessException('Chỉ sửa được tài khoản nhân viên ở đây.');
            }
            $attrs = ['name' => $name, 'permissions' => $this->clean($permissions), 'is_active' => $active];
            if ($newPassword !== null && $newPassword !== '') {
                $attrs['password'] = Hash::make($newPassword);
                $attrs['remember_token'] = null;                             // đăng xuất "ghi nhớ đăng nhập" cũ
            }
            $staff->forceFill($attrs)->save();

            return $staff;
        }, 3);
    }

    /**
     * Chỉ giữ khóa quyền hợp lệ, không trùng, theo thứ tự cố định.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function clean(array $permissions): array
    {
        return array_values(array_filter(StaffPermission::values(), fn ($p) => in_array($p, $permissions, true)));
    }
}

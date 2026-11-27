<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;

/**
 * Sổ địa chỉ của khách. Đơn hàng lưu snapshot địa chỉ nên sửa / xóa ở đây không ảnh hưởng đơn cũ.
 * Mọi thao tác khóa dòng users trước (thứ tự khóa ADR-003): 2 thao tác đồng thời không tạo 2 địa chỉ "mặc định"
 * (UNIQUE user_addresses.default_key -> lỗi 500) và không vượt trần UserAddress::MAX_PER_USER.
 */
class AddressService
{
    /** @param array<string, mixed> $fields */
    public function create(User $user, array $fields, bool $makeDefault): UserAddress
    {
        return DB::transaction(function () use ($user, $fields, $makeDefault) {
            $this->lockOwner($user->id);
            if ($user->addresses()->count() >= UserAddress::MAX_PER_USER) {
                throw new BusinessException('Bạn đã lưu tối đa '.UserAddress::MAX_PER_USER.' địa chỉ. Hãy xóa bớt địa chỉ không dùng.');
            }
            $makeDefault = $makeDefault || ! $user->addresses()->exists();       // địa chỉ đầu tiên luôn là mặc định
            if ($makeDefault) {
                UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
            }

            return $user->addresses()->create($fields + ['is_default' => $makeDefault]);
        }, 3);
    }

    /** @param array<string, mixed> $fields */
    public function update(UserAddress $address, array $fields): UserAddress
    {
        return DB::transaction(function () use ($address, $fields) {
            $this->lockOwner($address->user_id);
            $address->update($fields);

            return $address;
        }, 3);
    }

    public function makeDefault(UserAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $this->lockOwner($address->user_id);
            UserAddress::where('user_id', $address->user_id)->update(['is_default' => false]);   // bỏ mặc định cũ TRƯỚC (UNIQUE)
            $address->update(['is_default' => true]);
        }, 3);
    }

    /** Xóa; nếu là địa chỉ mặc định thì địa chỉ mới nhất còn lại thành mặc định. */
    public function delete(UserAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $this->lockOwner($address->user_id);
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault) {
                UserAddress::where('user_id', $address->user_id)->latest('id')->first()?->update(['is_default' => true]);
            }
        }, 3);
    }

    /**
     * Đặt hàng xong: nhớ địa chỉ giao vào sổ (việc phụ). Đã có y hệt hoặc sổ đầy thì thôi — đơn đã có snapshot, không mất gì.
     * Chưa có địa chỉ mặc định thì địa chỉ này thành mặc định.
     *
     * @param  array<string, mixed>  $fields
     */
    public function rememberFromOrder(int $userId, array $fields): void
    {
        DB::transaction(function () use ($userId, $fields) {
            $this->lockOwner($userId);                                       // 2 đơn đầu đặt cùng lúc: không cùng thành "mặc định"
            $book = UserAddress::where('user_id', $userId);
            if ((clone $book)->where($fields)->exists() || (clone $book)->count() >= UserAddress::MAX_PER_USER) {
                return;
            }
            UserAddress::create($fields + ['user_id' => $userId, 'is_default' => ! (clone $book)->where('is_default', true)->exists()]);
        }, 3);
    }

    private function lockOwner(int $userId): void
    {
        User::whereKey($userId)->lockForUpdate()->firstOrFail();
    }
}

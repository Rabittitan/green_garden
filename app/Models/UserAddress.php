<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Sổ địa chỉ. default_key là cột sinh (UNIQUE) đảm bảo mỗi khách 1 địa chỉ mặc định: KHÔNG được ghi.
 *
 * @property int $id
 * @property int $user_id
 * @property string $recipient_name
 * @property string $phone
 * @property string $province
 * @property string $district
 * @property string $ward
 * @property string $address_line
 * @property bool $is_default
 * @property int|null $default_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserAddress extends Model
{
    /** Sổ địa chỉ không phân trang (hiện cả ở trang đặt hàng) -> giới hạn số lượng thay vì để phình vô hạn. */
    public const MAX_PER_USER = 10;

    protected $fillable = ['user_id', 'recipient_name', 'phone', 'province', 'district', 'ward', 'address_line', 'is_default'];

    protected $hidden = ['default_key'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', [$this->address_line, $this->ward, $this->district, $this->province]);
    }

    /**
     * Dữ liệu snapshot để chép sang đơn hàng.
     *
     * @return array<string, mixed>
     */
    public function toShippingSnapshot(): array
    {
        return $this->only(['recipient_name', 'phone', 'province', 'district', 'ward', 'address_line']);
    }
}

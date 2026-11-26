<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Luật tặng mã khi mua: đơn HOÀN THÀNH có tổng tiền >= min_order_total, đã qua hạn đổi trả, hoàn thành SAU khi
 * luật được tạo -> tặng 1 mã riêng (coupons.owner_user_id) theo mẫu dưới đây, hạn valid_days ngày.
 *
 * @property int $id
 * @property string $name
 * @property int $min_order_total
 * @property CouponType $type
 * @property int $value
 * @property int|null $max_discount_amount
 * @property int $min_order_amount
 * @property int $valid_days
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CouponGiftRule extends Model
{
    protected $fillable = ['name', 'min_order_total', 'type', 'value', 'max_discount_amount', 'min_order_amount', 'valid_days', 'is_active'];

    protected function casts(): array
    {
        return ['type' => CouponType::class, 'is_active' => 'boolean', 'min_order_total' => 'integer', 'value' => 'integer',
            'max_discount_amount' => 'integer', 'min_order_amount' => 'integer', 'valid_days' => 'integer'];
    }

    /** @return HasMany<Coupon, $this> mã đã tặng theo luật này */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'gift_rule_id');
    }
}

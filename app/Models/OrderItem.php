<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/** Dòng đơn hàng (snapshot tên, SKU, giá, giá vốn). line_subtotal là cột sinh: KHÔNG được ghi.
 *
 * @property int $id
 * @property int $order_id
 * @property int $plant_id
 * @property int|null $flash_sale_item_id
 * @property string $plant_name
 * @property string $plant_sku
 * @property int $unit_price
 * @property int $quantity
 * @property int|null $line_subtotal
 * @property int $member_discount
 * @property int $coupon_discount
 * @property int $unit_cost
 * @property int $returned_good_qty
 * @property int $returned_damaged_qty
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrderItem extends Model
{
    protected $guarded = ['id', 'line_subtotal'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer', 'quantity' => 'integer', 'line_subtotal' => 'integer',
            'member_discount' => 'integer', 'coupon_discount' => 'integer', 'unit_cost' => 'integer',
            'returned_good_qty' => 'integer', 'returned_damaged_qty' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    /** @return HasOne<PlantReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(PlantReview::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Thành tiền dòng sau giảm giá. */
    public function getLineTotalAttribute(): int
    {
        return $this->unit_price * $this->quantity - $this->member_discount - $this->coupon_discount;
    }
}

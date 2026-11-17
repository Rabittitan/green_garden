<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dòng đơn hàng (snapshot tên, SKU, giá, giá vốn). line_subtotal là cột sinh: KHÔNG được ghi. */
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

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    public function review()
    {
        return $this->hasOne(PlantReview::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Thành tiền dòng sau giảm giá. */
    public function getLineTotalAttribute(): int
    {
        return $this->unit_price * $this->quantity - $this->member_discount - $this->coupon_discount;
    }
}

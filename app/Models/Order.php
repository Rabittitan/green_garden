<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'code', 'user_id', 'customer_name', 'phone', 'address', 'note',
        'subtotal', 'discount_amount', 'shipping_fee', 'total', 'coupon_id', 'coupon_code',
        'status', 'payment_method', 'payment_status', 'paid_at', 'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    // Giữ hằng số cũ để các view hiện tại (Order::STATUSES[$order->status]) vẫn chạy
    const STATUSES = [
        'pending' => 'Chờ xử lý', 'shipping' => 'Đang giao',
        'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy',
    ];

    public function statusEnum(): OrderStatus { return OrderStatus::from($this->status); }

    public function user()       { return $this->belongsTo(User::class); }
    public function items()      { return $this->hasMany(OrderItem::class); }
    public function coupon()     { return $this->belongsTo(Coupon::class)->withTrashed(); }
    public function redemption() { return $this->hasOne(CouponRedemption::class); }
    public function histories()  { return $this->hasMany(OrderStatusHistory::class)->latest('created_at'); }

    /** Sinh mã đơn công khai: GL + yymmdd + 4 số ngẫu nhiên (unique index chặn trùng). */
    public static function generateCode(): string
    {
        do {
            $code = 'GL'.now()->format('ymd').random_int(1000, 9999);
        } while (static::where('code', $code)->exists());

        return $code;
    }
}

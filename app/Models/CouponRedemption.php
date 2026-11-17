<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'discount_amount', 'status', 'released_at'];

    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }

    public function coupon() { return $this->belongsTo(Coupon::class)->withTrashed(); }
    public function user()   { return $this->belongsTo(User::class); }
    public function order()  { return $this->belongsTo(Order::class); }
}

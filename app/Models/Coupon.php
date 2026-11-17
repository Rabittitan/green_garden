<?php

namespace App\Models;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    // used_count không cho gán hàng loạt: chỉ CouponService tăng/giảm trong transaction
    protected $fillable = [
        'code', 'name', 'description', 'type', 'value', 'max_discount_amount', 'min_order_amount',
        'scope', 'usage_limit', 'usage_limit_per_user', 'first_order_only',
        'starts_at', 'ends_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'scope' => CouponScope::class,
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** Mã luôn lưu IN HOA để "giam10" và "GIAM10" là một. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function plants()      { return $this->belongsToMany(Plant::class, 'coupon_plants'); }
    public function categories()  { return $this->belongsToMany(Category::class, 'coupon_categories'); }
    public function redemptions() { return $this->hasMany(CouponRedemption::class); }

    public function scopeUsableNow(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
    }
}

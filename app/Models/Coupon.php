<?php

namespace App\Models;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property CouponType $type
 * @property int $value
 * @property int|null $max_discount_amount
 * @property int $min_order_amount
 * @property CouponScope $scope
 * @property int|null $min_tier_id
 * @property bool $combinable_with_tier
 * @property int|null $usage_limit
 * @property int $usage_limit_per_user
 * @property int $used_count
 * @property bool $first_order_only
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $owner_user_id
 * @property int|null $gift_rule_id
 * @property int|null $source_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Coupon extends Model
{
    use SoftDeletes;

    // used_count không cho gán hàng loạt: chỉ CouponService tăng/giảm trong transaction có khóa dòng.
    protected $fillable = [
        'code', 'name', 'description', 'type', 'value', 'max_discount_amount', 'min_order_amount',
        'scope', 'min_tier_id', 'combinable_with_tier', 'usage_limit', 'usage_limit_per_user',
        'first_order_only', 'starts_at', 'ends_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'scope' => CouponScope::class,
            'value' => 'integer',
            'max_discount_amount' => 'integer',
            'min_order_amount' => 'integer',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'used_count' => 'integer',
            'combinable_with_tier' => 'boolean',
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** Mã luôn lưu IN HOA để "giam10" và "GIAM10" là một. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = static::normalizeCode($value);
    }

    /** @return BelongsTo<User, $this> chủ ví (mã riêng); null = mã chung */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Mã riêng của khách còn dùng được (bật, đã tới giờ, chưa hết hạn, còn lượt). Điều kiện đơn tối thiểu xét lúc áp mã.
     *
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeWalletUsable(Builder $q, int $userId): Builder
    {
        return $q->where('owner_user_id', $userId)->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($w) => $w->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
    }

    /** Mô tả ngắn quyền lợi, vd "Giảm 10% (tối đa 50.000đ)" / "Giảm 30.000đ" / "Miễn phí vận chuyển". */
    public function benefitText(): string
    {
        return match ($this->type) {
            CouponType::Percent => 'Giảm '.$this->value.'%'.($this->max_discount_amount ? ' (tối đa '.Format::money($this->max_discount_amount).')' : ''),
            CouponType::Fixed => 'Giảm '.Format::money($this->value),
            CouponType::FreeShipping => 'Miễn phí vận chuyển',
        };
    }

    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /** @return BelongsTo<MembershipTier, $this> */
    public function minTier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'min_tier_id');
    }

    /** @return BelongsToMany<Plant, $this> */
    public function plants(): BelongsToMany
    {
        return $this->belongsToMany(Plant::class, 'coupon_plants');
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_categories');
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Đã có người dùng thì không cho đổi code / loại / giá trị (chỉ được tắt hoặc xóa mềm). */
    public function isLocked(): bool
    {
        return $this->used_count > 0 || $this->redemptions()->exists();
    }
}

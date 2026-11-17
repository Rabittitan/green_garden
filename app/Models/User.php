<?php

namespace App\Models;

use App\Enums\TierChangeReason;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * role, membership_tier_id, lifetime_spend, tier_achieved_at, is_active KHÔNG cho gán hàng loạt:
 * chỉ Service (MembershipService, quản trị) đổi bằng forceFill để chống leo quyền / tự nâng hạng.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $phone
 * @property int $membership_tier_id
 * @property int $lifetime_spend
 * @property Carbon|null $tier_achieved_at
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone'];

    /**
     * Giá trị mặc định GIỐNG DB: model vừa create() (chưa đọc lại từ DB) vẫn có role / is_active,
     * nên isBackOffice(), middleware... không gặp null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['role' => 'customer', 'is_active' => true, 'lifetime_spend' => 0];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'lifetime_spend' => 'integer',
            'tier_achieved_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Bất biến: mọi tài khoản luôn có hạng + 1 dòng lịch sử 'initial'.
        static::creating(function (User $user) {
            if (! $user->membership_tier_id) {
                $user->membership_tier_id = MembershipTier::base()->id;
                $user->tier_achieved_at ??= now();
            }
        });

        static::created(function (User $user) {
            MembershipTierHistory::create([
                'user_id' => $user->id, 'to_tier_id' => $user->membership_tier_id,
                'reason' => TierChangeReason::Initial, 'spend_total' => (int) $user->lifetime_spend,
            ]);
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isBackOffice(): bool
    {
        return $this->role->isBackOffice();
    }

    /** @return BelongsTo<MembershipTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id');
    }

    /** @return HasMany<MembershipTierHistory, $this> */
    public function tierHistories(): HasMany
    {
        return $this->hasMany(MembershipTierHistory::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<UserAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class)->orderByDesc('is_default')->latest('id');
    }

    /** @return HasOne<UserAddress, $this> */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<PlantReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(PlantReview::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}

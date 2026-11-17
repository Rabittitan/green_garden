<?php

namespace App\Models;

use App\Enums\TierChangeReason;
use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * role, membership_tier_id, lifetime_spend, tier_achieved_at, is_active KHÔNG cho gán hàng loạt:
 * chỉ Service (MembershipService, quản trị) đổi bằng forceFill để chống leo quyền / tự nâng hạng.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone'];

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
        return $this->role?->isBackOffice() ?? false;
    }

    public function tier()
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id');
    }

    public function tierHistories()
    {
        return $this->hasMany(MembershipTierHistory::class)->latest('created_at')->latest('id');
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class)->orderByDesc('is_default')->latest('id');
    }

    public function defaultAddress()
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(PlantReview::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function couponRedemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }
}

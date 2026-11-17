<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Hạng thành viên. Hạng xét theo tổng chi tiêu trọn đời (users.lifetime_spend), chỉ đi lên. */
class MembershipTier extends Model
{
    protected $fillable = [
        'code', 'name', 'rank', 'min_spend', 'discount_percent',
        'max_discount_per_order', 'free_shipping_min_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer', 'min_spend' => 'integer', 'discount_percent' => 'integer',
            'max_discount_per_order' => 'integer', 'free_shipping_min_order' => 'integer', 'is_active' => 'boolean',
        ];
    }

    public function users() { return $this->hasMany(User::class); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    public function scopeOrdered(Builder $q): Builder { return $q->orderBy('rank'); }

    /** Hạng cơ bản (rank 0) — mọi tài khoản mới nhận hạng này. */
    public static function base(): self
    {
        return static::where('rank', 0)->firstOrFail();
    }

    /** Hạng cao nhất (đang mở) mà mức chi tiêu này đạt được. */
    public static function forSpend(int $spend): self
    {
        return static::active()->where('min_spend', '<=', $spend)->orderByDesc('rank')->firstOrFail();
    }

    /** Hạng kế tiếp để hiển thị "còn X đ nữa lên hạng". */
    public function next(): ?self
    {
        return static::active()->where('rank', '>', $this->rank)->orderBy('rank')->first();
    }

    public function isHigherThan(self $other): bool
    {
        return $this->rank > $other->rank;
    }
}

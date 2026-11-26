<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Vùng phí ship: phí = base_fee cho base_weight_grams đầu + extra_fee_per_kg × số kg (làm tròn lên) vượt mức.
 *
 * @property int $id
 * @property string $name
 * @property int $base_fee
 * @property int $base_weight_grams
 * @property int $extra_fee_per_kg
 * @property bool $is_default
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShippingZone extends Model
{
    protected $fillable = ['name', 'base_fee', 'base_weight_grams', 'extra_fee_per_kg', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return ['base_fee' => 'integer', 'base_weight_grams' => 'integer', 'extra_fee_per_kg' => 'integer', 'is_default' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<ShippingZoneProvince, $this> */
    public function provinces(): HasMany
    {
        return $this->hasMany(ShippingZoneProvince::class);
    }

    /** Phí cho tổng khối lượng (gram) của đơn. */
    public function feeFor(int $weightGrams): int
    {
        $extraKg = (int) ceil(max(0, $weightGrams - $this->base_weight_grams) / 1000);

        return $this->base_fee + $extraKg * $this->extra_fee_per_kg;
    }
}

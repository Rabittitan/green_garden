<?php

namespace App\Services;

use App\Models\MembershipTier;
use App\Models\ShippingZone;
use App\Models\ShippingZoneProvince;
use Illuminate\Support\Collection;

/**
 * Phí vận chuyển.
 *  - Tiền hàng (sau giảm) đạt ngưỡng miễn ship (chung hoặc riêng của hạng) -> 0đ.
 *  - Có cấu hình vùng (Admin → Phí vận chuyển): vùng của tỉnh nhận hàng (chưa gán -> vùng mặc định),
 *    phí = phí cơ bản cho N gram đầu + phí mỗi kg (làm tròn lên) vượt mức. Khối lượng = Σ khối lượng đóng gói × SL.
 *  - Chưa có vùng nào: phí đồng giá shop.shipping.base_fee như trước.
 */
class ShippingFeeCalculator
{
    /** @var array{zones: Collection<int, ShippingZone>, map: array<string, int>}|null nạp 1 lần / request */
    private ?array $cache = null;

    public function freeShippingThreshold(MembershipTier $tier): int
    {
        return $tier->free_shipping_min_order ?? (int) config('shop.shipping.free_min_order');
    }

    public function fee(int $merchandiseTotal, MembershipTier $tier, ?string $province = null, int $weightGrams = 0): int
    {
        if ($merchandiseTotal >= $this->freeShippingThreshold($tier)) {
            return 0;
        }
        $zone = $this->zoneFor($province);

        return $zone ? $zone->feeFor($weightGrams) : (int) config('shop.shipping.base_fee');
    }

    /** Vùng áp cho tỉnh (null = chưa cấu hình vùng nào -> đồng giá). */
    public function zoneFor(?string $province): ?ShippingZone
    {
        $this->cache ??= [
            'zones' => ShippingZone::orderBy('sort_order')->orderBy('id')->get()->keyBy('id'),
            'map' => ShippingZoneProvince::pluck('shipping_zone_id', 'province')->map(fn ($v) => (int) $v)->all(),
        ];
        if ($this->cache['zones']->isEmpty()) {
            return null;
        }
        $id = $province !== null ? ($this->cache['map'][$province] ?? null) : null;

        return ($id !== null ? $this->cache['zones']->get($id) : null)
            ?? $this->cache['zones']->firstWhere('is_default', true)
            ?? $this->cache['zones']->first();
    }

    /** Admin vừa sửa vùng trong cùng request (test) -> nạp lại. */
    public function forget(): void
    {
        $this->cache = null;
    }
}

<?php

namespace App\Services;

use App\Models\MembershipTier;

/** Phí vận chuyển: đồng giá, miễn phí khi tiền hàng (sau giảm) đạt ngưỡng; hạng thành viên có ngưỡng riêng. */
class ShippingFeeCalculator
{
    public function freeShippingThreshold(MembershipTier $tier): int
    {
        return $tier->free_shipping_min_order ?? (int) config('shop.shipping.free_min_order');
    }

    public function fee(int $merchandiseTotal, MembershipTier $tier): int
    {
        return $merchandiseTotal >= $this->freeShippingThreshold($tier) ? 0 : (int) config('shop.shipping.base_fee');
    }
}

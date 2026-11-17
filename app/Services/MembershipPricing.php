<?php

namespace App\Services;

use App\Models\MembershipTier;

/** Ưu đãi giá theo hạng (phần tính toán thuần, không chạm DB). */
class MembershipPricing
{
    public function discountFor(MembershipTier $tier, int $subtotal): int
    {
        if (! $tier->is_active || $tier->discount_percent === 0 || $subtotal <= 0) {
            return 0;
        }
        $discount = intdiv($subtotal * $tier->discount_percent, 100);
        if ($tier->max_discount_per_order !== null) {
            $discount = min($discount, $tier->max_discount_per_order);
        }

        return min($discount, $subtotal);
    }
}

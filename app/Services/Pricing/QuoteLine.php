<?php

namespace App\Services\Pricing;

use App\Models\Plant;

/** Một dòng trong báo giá. Giá luôn lấy từ DB tại thời điểm báo giá. */
final class QuoteLine
{
    public int $memberDiscount = 0;
    public int $couponDiscount = 0;

    public function __construct(
        public readonly Plant $plant,
        public readonly int $quantity,
        public readonly int $unitPrice,
    ) {}

    public function subtotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    /** Thành tiền sau giảm theo hạng (cơ sở để tính mã giảm %). */
    public function afterMemberDiscount(): int
    {
        return $this->subtotal() - $this->memberDiscount;
    }

    public function total(): int
    {
        return $this->subtotal() - $this->memberDiscount - $this->couponDiscount;
    }
}

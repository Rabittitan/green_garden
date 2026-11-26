<?php

namespace App\Services\Pricing;

use App\Models\FlashSaleItem;
use App\Models\Plant;

/** Một dòng trong báo giá. Giá luôn lấy từ DB tại thời điểm báo giá. */
final class QuoteLine
{
    public int $memberDiscount = 0;

    public int $couponDiscount = 0;

    /**
     * @param  FlashSaleItem|null  $flashItem  suất flash sale ĐÃ ÁP cho dòng này (đơn giá = giá sale), null = giá thường
     */
    public function __construct(
        public readonly Plant $plant,
        public readonly int $quantity,
        public readonly int $unitPrice,
        public readonly ?FlashSaleItem $flashItem = null,
    ) {}

    /** Giá niêm yết của cây (để hiện giá gạch khi đang sale). */
    public function listPrice(): int
    {
        return (int) $this->plant->price;
    }

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

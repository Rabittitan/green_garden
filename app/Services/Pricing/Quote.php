<?php

namespace App\Services\Pricing;

use App\Models\Coupon;
use App\Models\MembershipTier;
use LogicException;

/**
 * Kết quả báo giá — dùng CHUNG cho trang xem lại đơn, nút "Áp mã" và lúc đặt hàng,
 * nên con số khách thấy và con số ghi vào đơn luôn do cùng một công thức tạo ra.
 *
 * total = subtotal - memberDiscount - couponDiscount + shippingFee - shippingDiscount
 */
final class Quote
{
    /** @param list<QuoteLine> $lines  @param list<string> $notices */
    public function __construct(
        public readonly array $lines,
        public readonly MembershipTier $tier,
        public readonly int $memberDiscountPercent,
        public readonly ?Coupon $coupon,
        public readonly int $shippingFee,
        public readonly int $shippingDiscount,
        public readonly array $notices = [],
    ) {
        $this->assertInvariants();
    }

    public function subtotal(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->subtotal(), $this->lines));
    }

    public function memberDiscount(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->memberDiscount, $this->lines));
    }

    public function couponDiscount(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->couponDiscount, $this->lines));
    }

    public function merchandiseTotal(): int
    {
        return $this->subtotal() - $this->memberDiscount() - $this->couponDiscount();
    }

    public function total(): int
    {
        return $this->merchandiseTotal() + $this->shippingFee - $this->shippingDiscount;
    }

    /** Tổng ưu đãi từ mã (giá trị ghi vào coupon_redemptions.discount_amount). */
    public function couponBenefit(): int
    {
        return $this->couponDiscount() + $this->shippingDiscount;
    }

    public function line(int $plantId): ?QuoteLine
    {
        foreach ($this->lines as $l) {
            if ($l->plant->id === $plantId) {
                return $l;
            }
        }

        return null;
    }

    /** Bảo vệ cuối: báo giá sai thì nổ ở đây, trước khi chạm DB. */
    private function assertInvariants(): void
    {
        if ($this->lines === []) {
            throw new LogicException('Báo giá phải có ít nhất 1 dòng.');
        }
        foreach ($this->lines as $l) {
            if ($l->quantity < 1 || $l->memberDiscount < 0 || $l->couponDiscount < 0 || $l->total() < 0) {
                throw new LogicException("Dòng báo giá không hợp lệ (cây #{$l->plant->id}).");
            }
        }
        if ($this->shippingDiscount < 0 || $this->shippingDiscount > $this->shippingFee) {
            throw new LogicException('Giảm phí ship không hợp lệ.');
        }
        if ($this->coupon === null && ($this->couponDiscount() > 0 || $this->shippingDiscount > 0)) {
            throw new LogicException('Có tiền giảm theo mã nhưng không có mã.');
        }
    }
}

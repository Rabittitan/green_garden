<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\CouponNotApplicableException;
use App\Models\Coupon;
use App\Models\Plant;
use App\Models\User;
use App\Services\Pricing\Quote;
use App\Services\Pricing\QuoteLine;
use App\Support\Allocator;

/**
 * Báo giá — MỘT công thức duy nhất cho cả xem trước và đặt hàng:
 *
 *   1. subtotal          = Σ đơn giá (DB) × số lượng
 *   2. member_discount   = min(floor(subtotal × %hạng), trần hạng)          → phân bổ theo thành tiền dòng
 *   3. coupon_discount   = theo mã, tính trên (dòng − giảm hạng của dòng) trong phạm vi mã → phân bổ
 *        mã KHÔNG cộng dồn với hạng: tính cả 2 phương án, chọn phương án lợi hơn cho khách
 *        (bằng nhau thì giữ ưu đãi hạng để khách không tốn lượt dùng mã)
 *   4. shipping_fee      = phí theo (tiền hàng sau giảm) và ngưỡng miễn ship của hạng
 *   5. shipping_discount = mã free_shipping (≤ phí ship)
 *   6. total             = subtotal − member − coupon + shipping_fee − shipping_discount
 *
 * Mã được áp mà không mang lại đồng nào -> từ chối (khách không bị tốn lượt).
 * Service này KHÔNG khóa và KHÔNG ghi DB; OrderService truyền vào cây/mã đã khóa khi đặt hàng.
 */
class PricingService
{
    public function __construct(
        private CouponService $coupons,
        private MembershipPricing $membership,
        private ShippingFeeCalculator $shipping,
    ) {}

    /**
     * Báo giá cho giỏ hàng trong session (xem trước): tự nạp cây, tự tra mã (không khóa).
     *
     * @param  array<int,int>  $cart  plant_id => quantity
     */
    public function quoteCart(User $user, array $cart, ?string $couponCode = null): Quote
    {
        $plants = Plant::whereIn('id', array_keys($cart))->get()->keyBy('id');
        $items = [];
        foreach ($cart as $plantId => $qty) {
            $plant = $plants->get($plantId);
            if (! $plant || ! $plant->is_active) {
                throw new BusinessException('Có cây trong giỏ đã ngừng bán, vui lòng kiểm tra lại giỏ hàng.');
            }
            $items[] = ['plant' => $plant, 'quantity' => (int) $qty];
        }
        $coupon = $couponCode !== null && trim($couponCode) !== '' ? $this->coupons->find($couponCode) : null;

        return $this->quote($user, $items, $coupon);
    }

    /** @param list<array{plant: Plant, quantity: int}> $items */
    public function quote(User $user, array $items, ?Coupon $coupon = null): Quote
    {
        if ($items === []) {
            throw new BusinessException('Giỏ hàng đang trống!');
        }
        foreach ($items as $it) {
            if ($it['quantity'] < 1) {
                throw new BusinessException('Số lượng phải lớn hơn 0.');
            }
        }
        usort($items, fn ($a, $b) => $a['plant']->id <=> $b['plant']->id);   // thứ tự ổn định -> phân bổ tái lập được

        if ($coupon === null) {
            return $this->scenario($user, $items, withMember: true, coupon: null);
        }

        $this->coupons->assertUsable($coupon, $user, $this->freshLines($items));

        if ($coupon->combinable_with_tier) {
            $quote = $this->scenario($user, $items, withMember: true, coupon: $coupon);
        } else {
            $memberOnly = $this->scenario($user, $items, withMember: true, coupon: null);
            $couponOnly = $this->scenario($user, $items, withMember: false, coupon: $coupon);
            if ($memberOnly->total() <= $couponOnly->total() && $memberOnly->memberDiscount() > 0) {
                return $this->scenario($user, $items, withMember: true, coupon: null, notices: [
                    "Mã {$coupon->code} không cộng dồn với ưu đãi hạng {$user->tier->name}; hệ thống giữ ưu đãi hạng vì có lợi hơn cho bạn.",
                ]);
            }
            $quote = $couponOnly;
        }

        if ($quote->couponBenefit() === 0) {
            throw new CouponNotApplicableException("Mã {$coupon->code} không mang lại ưu đãi nào cho đơn hàng này.");
        }

        return $quote;
    }

    /** @param list<array{plant: Plant, quantity: int}> $items  @param list<string> $notices */
    private function scenario(User $user, array $items, bool $withMember, ?Coupon $coupon, array $notices = []): Quote
    {
        $tier = $user->tier;
        $lines = $this->freshLines($items);
        $subtotal = array_sum(array_map(fn (QuoteLine $l) => $l->subtotal(), $lines));

        $memberDiscount = $withMember ? $this->membership->discountFor($tier, $subtotal) : 0;
        $weights = array_map(fn (QuoteLine $l) => $l->subtotal(), $lines);
        foreach (Allocator::allocate($memberDiscount, $weights) as $i => $share) {
            $lines[$i]->memberDiscount = $share;
        }

        $couponDiscount = $coupon ? $this->coupons->applyToLines($coupon, $lines) : 0;
        $merchandise = $subtotal - $memberDiscount - $couponDiscount;
        $fee = $this->shipping->fee($merchandise, $tier);
        $shippingDiscount = $coupon ? $this->coupons->shippingDiscount($coupon, $fee) : 0;

        return new Quote(
            lines: $lines,
            tier: $tier,
            memberDiscountPercent: $memberDiscount > 0 ? $tier->discount_percent : 0,
            coupon: $coupon,
            shippingFee: $fee,
            shippingDiscount: $shippingDiscount,
            notices: $notices,
        );
    }

    /** @param list<array{plant: Plant, quantity: int}> $items  @return list<QuoteLine> */
    private function freshLines(array $items): array
    {
        return array_map(fn ($it) => new QuoteLine($it['plant'], $it['quantity'], (int) $it['plant']->price), $items);
    }
}

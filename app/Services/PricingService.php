<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\CouponNotApplicableException;
use App\Models\Coupon;
use App\Models\FlashSaleItem;
use App\Models\Plant;
use App\Models\User;
use App\Services\Pricing\Quote;
use App\Services\Pricing\QuoteLine;
use App\Support\Allocator;

/**
 * Báo giá — MỘT công thức duy nhất cho cả xem trước và đặt hàng:
 *
 *   1. subtotal          = Σ đơn giá × số lượng; đơn giá = giá sale nếu cây đang trong flash sale ĐÃ DUYỆT, còn đủ suất và giá sale < giá thường
 *                          cho cả số lượng của dòng, ngược lại = giá bán (DB). Giảm hạng + mã tính TIẾP trên giá này.
 *   2. member_discount   = min(floor(subtotal × %hạng), trần hạng)          → phân bổ theo thành tiền dòng
 *   3. coupon_discount   = theo mã, tính trên (dòng − giảm hạng của dòng) trong phạm vi mã → phân bổ
 *        mã KHÔNG cộng dồn với hạng: tính cả 2 phương án, chọn phương án lợi hơn cho khách
 *        (bằng nhau thì giữ ưu đãi hạng để khách không tốn lượt dùng mã)
 *   4. shipping_fee      = 0 nếu tiền hàng sau giảm đạt ngưỡng miễn ship của hạng; ngược lại theo VÙNG của tỉnh nhận hàng
 *                          + tổng khối lượng đóng gói (ShippingFeeCalculator; chưa cấu hình vùng -> đồng giá)
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
        private FlashSaleService $flashSales,
    ) {}

    /**
     * Báo giá cho giỏ hàng trong session (xem trước): tự nạp cây, tự tra mã (không khóa).
     *
     * @param  array<int,int>  $cart  plant_id => quantity
     */
    public function quoteCart(User $user, array $cart, ?string $couponCode = null, ?string $province = null): Quote
    {
        $plants = Plant::whereIn('id', array_keys($cart))->get()->keyBy('id');
        $flash = $this->flashSales->activeItems(array_map('intval', array_keys($cart)));   // xem trước: không khóa
        $items = [];
        foreach ($cart as $plantId => $qty) {
            $plant = $plants->get($plantId);
            if (! $plant || ! $plant->is_active) {
                throw new BusinessException('Có cây trong giỏ đã ngừng bán, vui lòng kiểm tra lại giỏ hàng.');
            }
            $items[] = ['plant' => $plant, 'quantity' => (int) $qty, 'flash' => $flash->get((int) $plantId)];
        }
        $coupon = $couponCode !== null && trim($couponCode) !== '' ? $this->coupons->find($couponCode) : null;

        return $this->quote($user, $items, $coupon, $province);
    }

    /** @param list<array{plant: Plant, quantity: int, flash?: FlashSaleItem|null}> $items */
    public function quote(User $user, array $items, ?Coupon $coupon = null, ?string $province = null): Quote
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
        $flashNotices = $this->flashNotices($items);

        if ($coupon === null) {
            return $this->scenario($user, $items, province: $province, withMember: true, coupon: null, notices: $flashNotices);
        }

        $this->coupons->assertUsable($coupon, $user, $this->freshLines($items));

        if ($coupon->combinable_with_tier) {
            $quote = $this->scenario($user, $items, province: $province, withMember: true, coupon: $coupon, notices: $flashNotices);
        } else {
            $memberOnly = $this->scenario($user, $items, province: $province, withMember: true, coupon: null);
            $couponOnly = $this->scenario($user, $items, province: $province, withMember: false, coupon: $coupon, notices: $flashNotices);
            if ($memberOnly->total() <= $couponOnly->total() && $memberOnly->memberDiscount() > 0) {
                return $this->scenario($user, $items, province: $province, withMember: true, coupon: null, notices: [...$flashNotices,
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

    /**
     * @param  list<array{plant: Plant, quantity: int, flash?: FlashSaleItem|null}>  $items
     * @param  list<string>  $notices
     */
    private function scenario(User $user, array $items, ?string $province, bool $withMember, ?Coupon $coupon, array $notices = []): Quote
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
        $weight = array_sum(array_map(fn (QuoteLine $l) => ($l->plant->weight_grams ?? (int) config('shop.shipping.default_weight_grams')) * $l->quantity, $lines));
        $fee = $this->shipping->fee($merchandise, $tier, $province, $weight);
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

    /**
     * @param  list<array{plant: Plant, quantity: int, flash?: FlashSaleItem|null}>  $items
     * @return list<QuoteLine>
     */
    private function freshLines(array $items): array
    {
        return array_map(function ($it) {
            [$unit, $applied] = self::unitPrice($it['plant'], $it['quantity'], $it['flash'] ?? null);

            return new QuoteLine($it['plant'], $it['quantity'], $unit, $applied);
        }, $items);
    }

    /**
     * Đơn giá 1 dòng — quy tắc DUY NHẤT (báo giá, đặt hàng, giỏ hàng): giá sale khi đợt sale còn đủ suất cho CẢ dòng
     * VÀ giá sale thấp hơn giá thường hiện tại; ngược lại giá thường. Khách luôn trả giá thấp hơn trong hai giá.
     *
     * @return array{0: int, 1: FlashSaleItem|null} [đơn giá, suất sale được áp]
     */
    public static function unitPrice(Plant $plant, int $quantity, ?FlashSaleItem $flash): array
    {
        $applied = $flash !== null && $flash->covers($quantity) && $flash->sale_price < (int) $plant->price ? $flash : null;

        return [$applied ? $applied->sale_price : (int) $plant->price, $applied];
    }

    /**
     * Cây đang sale nhưng số lượng vượt số suất còn lại -> tính giá thường, báo khách.
     *
     * @param  list<array{plant: Plant, quantity: int, flash?: FlashSaleItem|null}>  $items
     * @return list<string>
     */
    private function flashNotices(array $items): array
    {
        $notices = [];
        foreach ($items as $it) {
            $flash = $it['flash'] ?? null;
            if ($flash !== null && ! $flash->covers($it['quantity'])) {
                $notices[] = "Flash sale \"{$it['plant']->name}\" chỉ còn {$flash->remaining()} suất: dòng này đang tính giá thường. Giảm số lượng để hưởng giá sale.";
            }
        }

        return $notices;
    }
}

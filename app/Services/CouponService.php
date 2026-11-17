<?php

namespace App\Services;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kiểm tra, tính tiền giảm và ghi nhận lượt dùng phiếu giảm giá.
 *
 * $lines: [['plant_id'=>1, 'category_id'=>2, 'price'=>250000, 'quantity'=>2], ...]
 * (giá lấy từ DB, KHÔNG lấy từ session)
 */
class CouponService
{
    /**
     * Gọi BÊN TRONG transaction đặt hàng. Coupon bị khóa dòng để 2 người cùng dùng
     * lượt cuối không thể cùng thành công.
     *
     * @return array{coupon: Coupon, discount: int, shipping_discount: int, allocations: array<int,int>}
     */
    public function lockAndQuote(string $code, User $user, array $lines, int $shippingFee): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->lockForUpdate()->first();

        if (! $coupon || ! $coupon->is_active) {
            throw new RuntimeException('Mã giảm giá không tồn tại hoặc đã ngừng.');
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new RuntimeException('Mã giảm giá chưa tới thời gian sử dụng.');
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw new RuntimeException('Mã giảm giá đã hết hạn.');
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new RuntimeException('Mã giảm giá đã hết lượt.');
        }

        $usedByUser = CouponRedemption::where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)->where('status', 'applied')->count();
        if ($usedByUser >= $coupon->usage_limit_per_user) {
            throw new RuntimeException('Bạn đã dùng hết lượt cho mã này.');
        }

        if ($coupon->first_order_only && $user->orders()->where('status', '!=', 'cancelled')->exists()) {
            throw new RuntimeException('Mã chỉ dành cho đơn hàng đầu tiên.');
        }

        $orderSubtotal = array_sum(array_map(fn ($l) => $l['price'] * $l['quantity'], $lines));
        if ($orderSubtotal < $coupon->min_order_amount) {
            throw new RuntimeException('Đơn hàng chưa đạt giá trị tối thiểu '.number_format($coupon->min_order_amount, 0, ',', '.').'đ.');
        }

        // Lọc các dòng hàng thuộc phạm vi áp dụng
        $eligible = match ($coupon->scope) {
            CouponScope::Order => $lines,
            CouponScope::Plants => $this->filterLines($lines, 'plant_id',
                $coupon->plants()->pluck('plants.id')->map(fn ($id) => (int) $id)->all()),
            CouponScope::Categories => $this->filterLines($lines, 'category_id',
                $coupon->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all()),
        };
        if (! $eligible) {
            throw new RuntimeException('Mã không áp dụng cho sản phẩm trong giỏ.');
        }
        $eligibleSubtotal = array_sum(array_map(fn ($l) => $l['price'] * $l['quantity'], $eligible));

        $discount = 0;
        $shippingDiscount = 0;
        switch ($coupon->type) {
            case CouponType::Percent:
                $discount = intdiv($eligibleSubtotal * $coupon->value, 100);
                break;
            case CouponType::Fixed:
                $discount = $coupon->value;
                break;
            case CouponType::FreeShipping:
                $shippingDiscount = $shippingFee;
                break;
        }
        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, $coupon->max_discount_amount);
            $shippingDiscount = min($shippingDiscount, $coupon->max_discount_amount);
        }
        $discount = min($discount, $eligibleSubtotal);   // không bao giờ giảm quá tiền hàng

        return [
            'coupon' => $coupon,
            'discount' => $discount,
            'shipping_discount' => $shippingDiscount,
            'allocations' => $this->allocate($discount, $eligible),
        ];
    }

    private function filterLines(array $lines, string $key, array $allowedIds): array
    {
        return array_values(array_filter($lines, fn ($l) => in_array((int) $l[$key], $allowedIds, true)));
    }

    /**
     * Phân bổ tiền giảm xuống từng dòng theo tỷ lệ thành tiền (phương pháp phần dư lớn nhất),
     * đảm bảo tổng phân bổ = đúng $discount, không lệch 1đ.
     *
     * @return array<int,int> plant_id => discount
     */
    public function allocate(int $discount, array $lines): array
    {
        $total = array_sum(array_map(fn ($l) => $l['price'] * $l['quantity'], $lines));
        if ($discount === 0 || $total === 0) {
            return [];
        }

        $result = [];
        $remainders = [];
        foreach ($lines as $l) {
            $exact = $discount * $l['price'] * $l['quantity'] / $total;
            $result[$l['plant_id']] = (int) floor($exact);
            $remainders[$l['plant_id']] = $exact - floor($exact);
        }
        arsort($remainders);
        $left = $discount - array_sum($result);
        foreach (array_keys($remainders) as $plantId) {
            if ($left-- <= 0) {
                break;
            }
            $result[$plantId]++;
        }

        return $result;
    }

    /** Ghi nhận lượt dùng. Gọi trong cùng transaction với lockAndQuote, sau khi đã tạo Order. */
    public function redeem(Coupon $coupon, Order $order, int $totalDiscount): CouponRedemption
    {
        $coupon->increment('used_count');

        return CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'discount_amount' => $totalDiscount,
            'status' => 'applied',
        ]);
    }

    /** Hủy đơn: trả lại lượt dùng mã (tùy chính sách shop có muốn trả hay không). */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $redemption = CouponRedemption::where('order_id', $order->id)
                ->where('status', 'applied')->lockForUpdate()->first();
            if (! $redemption) {
                return;
            }
            $redemption->update(['status' => 'released', 'released_at' => now()]);
            Coupon::withTrashed()->whereKey($redemption->coupon_id)
                ->where('used_count', '>', 0)->decrement('used_count');
        });
    }
}

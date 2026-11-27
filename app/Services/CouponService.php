<?php

namespace App\Services;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\RedemptionStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\CouponNotApplicableException;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use App\Services\Pricing\QuoteLine;
use App\Support\Allocator;
use App\Support\Format;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Phiếu giảm giá.
 *  - find():            tra mã (tùy chọn KHÓA DÒNG khi gọi trong transaction đặt hàng)
 *  - assertUsable():    điều kiện về khách / thời gian / lượt dùng
 *  - applyToLines():    tính + phân bổ tiền giảm vào các dòng (percent / fixed)
 *  - shippingDiscount() : tiền giảm phí ship (free_shipping)
 *  - redeem() / release(): ghi nhận / trả lượt — gọi trong transaction đã khóa coupon
 *  - update():          admin sửa mã (khóa plants -> coupons như checkout; mã đã dùng thì giữ nguyên "luật")
 */
class CouponService
{
    /** Mã đã có người dùng: chỉ được đổi các cột này (không đổi code / loại / giá trị / phạm vi). */
    public const EDITABLE_WHEN_LOCKED = ['name', 'description', 'usage_limit', 'usage_limit_per_user', 'starts_at', 'ends_at', 'is_active'];

    public function __construct(private InventoryService $inventory) {}

    /**
     * Admin tạo mã. Cùng thứ tự khóa với sửa mã: plants (id tăng dần) trước, vì chèn coupon_plants khóa ngoại tới plants.
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<int, int>  $plantIds
     * @param  array<int, int>  $categoryIds
     */
    public function create(array $attrs, array $plantIds, array $categoryIds, User $by): Coupon
    {
        return DB::transaction(function () use ($attrs, $plantIds, $categoryIds, $by) {
            $this->inventory->lockPlants($plantIds);
            $coupon = Coupon::create($attrs + ['created_by' => $by->id]);
            $coupon->plants()->sync($plantIds);
            $coupon->categories()->sync($categoryIds);

            return $coupon;
        }, 3);
    }

    /** Xóa mềm: tắt rồi ẩn; đơn cũ vẫn tham chiếu được, mã không được tái sử dụng. */
    public function delete(Coupon $coupon): void
    {
        DB::transaction(function () use ($coupon) {
            $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $locked->update(['is_active' => false]);
            $locked->delete();
        }, 3);
    }

    /**
     * Admin sửa mã (nhóm B). Thứ tự khóa giống checkout / hủy đơn (ADR-003): plants (id tăng dần) -> coupons.
     * Trước đây khóa coupons rồi mới chạm plants (khóa ngoại khi sync coupon_plants) -> deadlock với checkout.
     * "Đã có người dùng" được xét MỘT lần, SAU khi khóa dòng mã, nên không thể lệch giữa lúc đổi luật và lúc đổi phạm vi.
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<int, int>  $plantIds
     * @param  array<int, int>  $categoryIds
     */
    public function update(Coupon $coupon, array $attrs, array $plantIds, array $categoryIds): Coupon
    {
        return DB::transaction(function () use ($coupon, $attrs, $plantIds, $categoryIds) {
            $this->inventory->lockPlants($plantIds);                         // khóa ngoại coupon_plants sẽ cần các dòng plants này
            $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $isLocked = $locked->isLocked();

            if ($isLocked) {                                                 // đã có người dùng: giữ nguyên "luật" giảm giá
                $attrs = Arr::only($attrs, self::EDITABLE_WHEN_LOCKED);
            }
            // used_count đọc SAU khi khóa: có khách vừa dùng mã trong lúc admin mở form -> báo lỗi thay vì vi phạm CHECK (lỗi 500)
            if (isset($attrs['usage_limit']) && (int) $attrs['usage_limit'] < $locked->used_count) {
                throw new BusinessException("Tổng lượt không được nhỏ hơn số lượt đã dùng ({$locked->used_count}).");
            }
            $locked->update($attrs);
            if (! $isLocked) {
                $locked->plants()->sync($plantIds);
                $locked->categories()->sync($categoryIds);
            }

            return $locked;
        }, 3);                                                               // thử lại khi deadlock, như mọi luồng ghi khác
    }

    public function find(string $code, bool $lock = false): Coupon
    {
        $query = Coupon::where('code', Coupon::normalizeCode($code));
        $coupon = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $coupon) {
            throw new CouponNotApplicableException('Mã giảm giá không tồn tại.');
        }

        return $coupon;
    }

    /** @param list<QuoteLine> $lines */
    public function assertUsable(Coupon $coupon, User $user, array $lines): void
    {
        if (! $coupon->is_active) {
            throw new CouponNotApplicableException('Mã giảm giá đã ngừng áp dụng.');
        }
        // Mã tặng riêng (ví mã của khách): chỉ chủ ví dùng được
        if ($coupon->owner_user_id !== null && $coupon->owner_user_id !== $user->id) {
            throw new CouponNotApplicableException('Mã này là quà tặng riêng của một tài khoản khác.');
        }
        if ($coupon->starts_at?->isFuture()) {
            throw new CouponNotApplicableException('Mã giảm giá chưa tới thời gian sử dụng.');
        }
        if ($coupon->ends_at?->isPast()) {
            throw new CouponNotApplicableException('Mã giảm giá đã hết hạn.');
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new CouponNotApplicableException('Mã giảm giá đã hết lượt sử dụng.');
        }
        if ($coupon->min_tier_id !== null) {
            $minTier = $coupon->minTier;
            if ($user->tier->rank < $minTier->rank) {
                throw new CouponNotApplicableException("Mã chỉ dành cho thành viên hạng {$minTier->name} trở lên.");
            }
        }

        $usedByUser = CouponRedemption::where('coupon_id', $coupon->id)->where('user_id', $user->id)
            ->where('status', RedemptionStatus::Applied->value)->count();
        if ($usedByUser >= $coupon->usage_limit_per_user) {
            throw new CouponNotApplicableException('Bạn đã dùng hết lượt cho mã này.');
        }

        if ($coupon->first_order_only
            && $user->orders()->where('status', '<>', OrderStatus::Cancelled->value)->exists()) {
            throw new CouponNotApplicableException('Mã chỉ dành cho đơn hàng đầu tiên.');
        }

        $subtotal = array_sum(array_map(fn (QuoteLine $l) => $l->subtotal(), $lines));
        if ($subtotal < $coupon->min_order_amount) {
            throw new CouponNotApplicableException('Đơn hàng chưa đạt giá trị tối thiểu '.Format::money($coupon->min_order_amount).' để dùng mã.');
        }

        if ($coupon->type !== CouponType::FreeShipping && $this->eligibleLines($coupon, $lines) === []) {
            throw new CouponNotApplicableException('Mã không áp dụng cho sản phẩm nào trong giỏ.');
        }
    }

    /**
     * Ghi tiền giảm theo mã vào $line->couponDiscount (percent/fixed). Trả tổng tiền giảm.
     * Tính trên thành tiền SAU giảm theo hạng của các dòng thuộc phạm vi mã.
     *
     * @param  list<QuoteLine>  $lines
     */
    public function applyToLines(Coupon $coupon, array $lines): int
    {
        foreach ($lines as $l) {
            $l->couponDiscount = 0;
        }
        if ($coupon->type === CouponType::FreeShipping) {
            return 0;
        }

        $eligible = $this->eligibleLines($coupon, $lines);
        $base = array_sum(array_map(fn (QuoteLine $l) => $l->afterMemberDiscount(), $eligible));

        $discount = match ($coupon->type) {
            CouponType::Percent => intdiv($base * $coupon->value, 100),
            CouponType::Fixed => $coupon->value,
        };
        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, $coupon->max_discount_amount);
        }
        $discount = min($discount, $base);                      // không bao giờ giảm quá tiền hàng

        $weights = [];
        foreach ($eligible as $i => $l) {
            $weights[$i] = $l->afterMemberDiscount();
        }
        foreach (Allocator::allocate($discount, $weights) as $i => $share) {
            $eligible[$i]->couponDiscount = $share;
        }

        return $discount;
    }

    public function shippingDiscount(Coupon $coupon, int $shippingFee): int
    {
        if ($coupon->type !== CouponType::FreeShipping) {
            return 0;
        }
        $discount = $shippingFee;
        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, $coupon->max_discount_amount);
        }

        return $discount;
    }

    /**
     * @param  list<QuoteLine>  $lines
     * @return list<QuoteLine>
     */
    public function eligibleLines(Coupon $coupon, array $lines): array
    {
        $ids = match ($coupon->scope) {
            CouponScope::Order => null,
            CouponScope::Plants => $coupon->plants()->pluck('plants.id')->map(fn ($id) => (int) $id)->all(),
            CouponScope::Categories => Category::withDescendantIds(
                $coupon->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all()
            ),
        };
        $key = $coupon->scope === CouponScope::Categories ? 'category_id' : 'id';

        return array_values(array_filter($lines, fn (QuoteLine $l) => $ids === null || in_array((int) $l->plant->{$key}, $ids, true)));
    }

    /** Ghi nhận lượt dùng. Gọi trong transaction đặt hàng, SAU khi đã khóa coupon và tạo đơn. */
    public function redeem(Coupon $coupon, Order $order, int $benefit): CouponRedemption
    {
        $coupon->increment('used_count');

        return CouponRedemption::create([
            'coupon_id' => $coupon->id, 'user_id' => $order->user_id, 'order_id' => $order->id,
            'discount_amount' => $benefit, 'status' => RedemptionStatus::Applied,
        ]);
    }

    /** Trả lượt khi hủy đơn. Gọi trong transaction đã khóa đơn (thứ tự khóa: order -> coupon). */
    public function release(Order $order): void
    {
        $redemption = CouponRedemption::where('order_id', $order->id)
            ->where('status', RedemptionStatus::Applied->value)->lockForUpdate()->first();
        if (! $redemption) {
            return;
        }
        Coupon::withTrashed()->whereKey($redemption->coupon_id)->lockForUpdate()->first();
        $redemption->update(['status' => RedemptionStatus::Released, 'released_at' => now()]);
        Coupon::withTrashed()->whereKey($redemption->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
    }
}

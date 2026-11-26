<?php

namespace App\Services;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\CouponGiftRule;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Account\CouponGifted;
use App\Support\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tặng mã giảm giá vào ví khách: tự động theo luật (CouponGiftRule) hoặc quản trị viên tặng tay.
 * Mã tặng là mã RIÊNG (coupons.owner_user_id): 1 lượt, chỉ chủ ví dùng được, mọi kiểm tra / phân bổ / trả lượt
 * khi hủy đơn đi qua CouponService như mã thường.
 */
class CouponGiftService
{
    /**
     * Chạy định kỳ (lệnh coupons:issue-gifts): tặng mã cho đơn đủ điều kiện mà chưa được tặng.
     * Đơn phải HOÀN THÀNH và đã qua hạn đổi trả (khách trả hàng thì không được quà), hoàn thành sau khi luật được tạo
     * (bật luật mới không tặng ngược cho đơn cũ). UNIQUE(gift_rule_id, source_order_id): chạy trùng / song song không tặng 2 lần.
     */
    public function issueDueGifts(int $limit = 200): int
    {
        $issued = 0;
        $dueBefore = now()->subDays((int) config('shop.return_window_days'));
        foreach (CouponGiftRule::where('is_active', true)->orderBy('id')->get() as $rule) {
            $orders = Order::with('user')->status(OrderStatus::Completed)
                ->where('completed_at', '<=', $dueBefore)->where('completed_at', '>=', $rule->created_at)
                ->where('total', '>=', $rule->min_order_total)
                ->whereNotExists(fn ($q) => $q->from('coupons')->whereColumn('coupons.source_order_id', 'orders.id')->where('coupons.gift_rule_id', $rule->id))
                ->orderBy('id')->limit($limit)->get();
            foreach ($orders as $order) {
                try {
                    $coupon = DB::transaction(fn () => $this->make($order->user_id, $rule->name, $rule->type, $rule->value,
                        $rule->max_discount_amount, $rule->min_order_amount, $rule->valid_days, null,
                        ['gift_rule_id' => $rule->id, 'source_order_id' => $order->id]), 3);
                    Notifier::send($order->user, new CouponGifted($coupon, "Cảm ơn bạn đã mua đơn {$order->code}."));
                    $issued++;
                } catch (UniqueConstraintViolationException) {
                    // tiến trình khác vừa tặng cho đơn này: bỏ qua
                }
            }
        }

        return $issued;
    }

    /**
     * Quản trị viên tặng tay 1 mã cho 1 khách (vd xin lỗi giao chậm, chăm sóc khách thân thiết).
     *
     * @param  array{name: string, type: string, value: int, max_discount_amount?: int|null, min_order_amount?: int, valid_days: int}  $data
     */
    public function giftManually(User $customer, array $data, User $admin): Coupon
    {
        if ($customer->role !== UserRole::Customer) {
            throw new BusinessException('Chỉ tặng mã cho tài khoản khách hàng.');
        }
        $type = CouponType::from($data['type']);
        $coupon = DB::transaction(fn () => $this->make($customer->id, $data['name'], $type, $type === CouponType::FreeShipping ? 0 : (int) $data['value'],
            $data['max_discount_amount'] ?? null, (int) ($data['min_order_amount'] ?? 0), (int) $data['valid_days'], $admin->id), 3);
        Notifier::send($customer, new CouponGifted($coupon, 'GreenGarden gửi tặng bạn một mã giảm giá.'));

        return $coupon;
    }

    /** @param array<string, int> $source */
    private function make(int $ownerId, string $name, CouponType $type, int $value, ?int $maxDiscount, int $minOrder, int $validDays, ?int $by, array $source = []): Coupon
    {
        $coupon = new Coupon([
            'code' => $this->uniqueCode(), 'name' => $name, 'type' => $type, 'value' => $type === CouponType::FreeShipping ? 0 : $value,
            'max_discount_amount' => $type === CouponType::Percent ? $maxDiscount : null, 'min_order_amount' => $minOrder,
            'scope' => CouponScope::Order, 'combinable_with_tier' => true, 'usage_limit' => 1, 'usage_limit_per_user' => 1,
            'starts_at' => now(), 'ends_at' => now()->addDays($validDays)->endOfDay(), 'is_active' => true, 'created_by' => $by,
        ]);
        $coupon->forceFill(['owner_user_id' => $ownerId] + $source)->save();

        return $coupon;
    }

    /** Mã khó đoán, không nhầm ký tự (bỏ 0/O, 1/I/L). */
    private function uniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = 'QUA'.collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (Coupon::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}

<?php

namespace Tests\Feature\Regression;

use App\Enums\PaymentStatus;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * Nhóm C (audit M5): công thức "chi tiêu của một đơn" tồn tại ở 2 nơi phải luôn khớp nhau:
 *   - Order::spend_contribution (PHP)          — trang "Hạng của tôi" dùng cho phần chưa chốt
 *   - MembershipService::spendOf() (SQL SUM)   — nguồn của users.lifetime_spend, xét hạng
 * Cả hai: max(0, paid − refunded − (shipping_fee − shipping_discount)), SQL chặn 0 TỪNG DÒNG rồi mới cộng.
 * Dữ liệu ghi thật vào MySQL (thỏa mọi CHECK của bảng orders), không mock; mỗi ca có thêm kết quả tính tay.
 */
class SpendContributionConsistencyTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    /** Ghi 1 đơn ĐÃ HOÀN THÀNH với số tiền cho trước (thỏa các CHECK chk_o_*), trả về model đọc lại từ DB. */
    private function completedOrder(User $user, int $subtotal, int $fee, int $paid, int $refunded = 0,
        int $shippingDiscount = 0, int $memberDiscount = 0, int $couponDiscount = 0, bool $recorded = true): Order
    {
        $total = $subtotal - $memberDiscount - $couponDiscount + $fee - $shippingDiscount;
        $now = now();
        $id = DB::table('orders')->insertGetId([
            'code' => 'T'.strtoupper(Str::random(12)), 'user_id' => $user->id, 'checkout_token' => (string) Str::uuid(),
            'status' => 'completed', 'recipient_name' => 'Khách test', 'phone' => '0900000009', 'province' => 'HCM',
            'district' => 'Q1', 'ward' => 'P1', 'address_line' => '1 Test', 'membership_tier_id' => MembershipTier::base()->id,
            'subtotal' => $subtotal, 'member_discount' => $memberDiscount, 'coupon_discount' => $couponDiscount,
            'shipping_fee' => $fee, 'shipping_discount' => $shippingDiscount, 'total' => $total,
            'payment_method' => 'cod', 'paid_amount' => $paid, 'refunded_amount' => $refunded,
            'payment_status' => PaymentStatus::derive($total, $paid, $refunded, false)->value,
            'paid_at' => $paid > 0 ? $now : null, 'shipping_carrier' => 'GHN', 'tracking_code' => 'T1',
            'placed_at' => $now, 'shipped_at' => $now, 'completed_at' => $now->copy()->subDays(10),
            'spend_recorded_at' => $recorded ? $now : null, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return Order::findOrFail($id);
    }

    private function spendOf(User $user): int
    {
        return app(MembershipService::class)->spendOf($user->id);
    }

    /** Tổng accessor trên ĐÚNG tập đơn mà spendOf() đếm: đơn của user đã chốt chi tiêu. */
    private function accessorSum(User $user): int
    {
        return (int) Order::where('user_id', $user->id)->whereNotNull('spend_recorded_at')->get()->sum('spend_contribution');
    }

    /**
     * subtotal, phí ship, đã thu, đã hoàn, giảm ship, giảm hạng, giảm mã, KẾT QUẢ TÍNH TAY
     *
     * @return array<string, array{int,int,int,int,int,int,int,int}>
     */
    public static function cases(): array
    {
        return [
            // 280.000 − 0 − 30.000
            'đơn bình thường' => [250000, 30000, 280000, 0, 0, 0, 0, 250000],
            // 280.000 − 100.000 − 30.000
            'hoàn tiền một phần' => [250000, 30000, 280000, 100000, 0, 0, 0, 150000],
            // mã miễn ship toàn phần: phí ship ròng = 0
            'giảm toàn bộ phí ship' => [250000, 30000, 250000, 0, 30000, 0, 0, 250000],
            // giảm ship một phần: 270.000 − 0 − (30.000 − 10.000)
            'giảm một phần phí ship' => [250000, 30000, 270000, 0, 10000, 0, 0, 250000],
            // 270.000 − 70.000 − 20.000
            'hoàn tiền + giảm phí ship' => [250000, 30000, 270000, 70000, 10000, 0, 0, 180000],
            // hoàn một phần + miễn ship toàn phần: 250.000 − 50.000 − 0
            'hoàn tiền + miễn ship toàn phần' => [250000, 30000, 250000, 50000, 30000, 0, 0, 200000],
            // giảm hạng/mã KHÔNG vào công thức trực tiếp, chỉ qua số đã thu: 850.000 − 0 − 0
            'có giảm hạng + giảm mã, không phí ship' => [1000000, 0, 850000, 0, 0, 50000, 100000, 850000],
            // ---- biên
            // hoàn toàn bộ: 280.000 − 280.000 − 30.000 = −30.000 -> chặn 0
            'biên: hoàn toàn bộ (âm -> 0)' => [250000, 30000, 280000, 280000, 0, 0, 0, 0],
            // 280.000 − 260.000 − 30.000 = −10.000 -> 0
            'biên: phần còn lại nhỏ hơn phí ship (âm -> 0)' => [250000, 30000, 280000, 260000, 0, 0, 0, 0],
            // 280.000 − 250.000 − 30.000 = 0 đúng
            'biên: đúng bằng 0' => [250000, 30000, 280000, 250000, 0, 0, 0, 0],
            // 280.000 − 249.999 − 30.000 = 1
            'biên: còn đúng 1đ' => [250000, 30000, 280000, 249999, 0, 0, 0, 1],
            // đơn 0đ (mã giảm hết, không phí ship): đã thu 0
            'biên: đơn 0đ' => [100000, 0, 0, 0, 0, 0, 100000, 0],
            // số lớn: 9.000.000.030.000 − 1 − 30.000
            'biên: số tiền rất lớn' => [9_000_000_000_000, 30000, 9_000_000_030_000, 1, 0, 0, 0, 8_999_999_999_999],
        ];
    }

    #[DataProvider('cases')]
    public function test_single_order_accessor_matches_sql_and_hand_calculation(int $subtotal, int $fee, int $paid, int $refunded,
        int $shippingDiscount, int $memberDiscount, int $couponDiscount, int $expected): void
    {
        $user = User::factory()->create();
        $order = $this->completedOrder($user, $subtotal, $fee, $paid, $refunded, $shippingDiscount, $memberDiscount, $couponDiscount);

        $this->assertSame($expected, $order->spend_contribution, 'Order::spend_contribution (PHP) lệch kết quả tính tay');
        $this->assertSame($expected, $this->spendOf($user), 'MembershipService::spendOf (SQL) lệch kết quả tính tay');
        $this->assertSame($this->spendOf($user), $this->accessorSum($user), 'PHP và SQL không khớp trên cùng dữ liệu');
    }

    /** SQL chặn 0 TỪNG ĐƠN rồi mới cộng: đơn âm không được trừ vào đơn khác (khác với chặn 0 sau khi cộng). */
    public function test_negative_order_is_clamped_per_order_not_after_summing(): void
    {
        $user = User::factory()->create();
        $this->completedOrder($user, 250000, 30000, 280000);                 // +250.000
        $this->completedOrder($user, 250000, 30000, 280000, refunded: 280000); // −30.000 -> 0

        $this->assertSame(250000, $this->spendOf($user), 'Nếu chặn 0 sau khi cộng sẽ ra 220.000');
        $this->assertSame(250000, $this->accessorSum($user));
    }

    /** Nhiều đơn đủ loại của cùng khách: tổng SQL = tổng accessor = tổng tính tay. */
    public function test_many_orders_sum_matches(): void
    {
        $user = User::factory()->create();
        $expected = 0;
        foreach (self::cases() as [$subtotal, $fee, $paid, $refunded, $sd, $md, $cd, $exp]) {
            $this->completedOrder($user, $subtotal, $fee, $paid, $refunded, $sd, $md, $cd);
            $expected += $exp;
        }

        $this->assertSame($expected, $this->spendOf($user));
        $this->assertSame($expected, $this->accessorSum($user));
    }

    /** spendOf chỉ đếm đơn ĐÃ CHỐT của ĐÚNG khách; accessor vẫn tính cho đơn chưa chốt (trang "Hạng của tôi" dùng). */
    public function test_scope_unrecorded_orders_and_other_customers_are_excluded(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->assertSame(0, $this->spendOf($user), 'Khách chưa có đơn: SUM rỗng phải ra 0');

        $this->completedOrder($user, 250000, 30000, 280000);                                  // đã chốt: 250.000
        $pending = $this->completedOrder($user, 400000, 30000, 430000, recorded: false);      // chưa chốt: 400.000
        $this->completedOrder($other, 999000, 0, 999000);                                     // khách khác

        $this->assertSame(250000, $this->spendOf($user));
        $this->assertSame(250000, $this->accessorSum($user));
        $this->assertSame(400000, $pending->spend_contribution);
    }

    /** "Đã hoàn vượt đã thu" bị DB chặn (chk_o_amounts) -> dữ liệu này không thể tồn tại để hai công thức phải xử lý. */
    public function test_refund_greater_than_paid_cannot_exist(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/chk_o_amounts/');
        $this->completedOrder(User::factory()->create(), 250000, 30000, 280000, refunded: 280001);
    }

    /** Dữ liệu sinh bởi CHÍNH các service (đặt hàng -> giao -> hoàn một phần -> chốt chi tiêu), không ghi tay. */
    public function test_real_flow_lifetime_spend_matches_both_formulas(): void
    {
        $orders = app(OrderService::class);
        $payments = app(PaymentService::class);
        $user = $this->customer();

        $order = $this->place($user, [1 => 2]);
        $orders->confirm($order, $this->admin());
        $orders->ship($order, 'GHN', 'FLOW-1', $this->admin());
        $orders->complete($order, $this->admin());
        $refund = $payments->requestRefund($order->refresh(), 40000, $this->admin(), 'Cây bị dập lá');
        $payments->confirmRefund($refund, $this->admin(), 'RF-FLOW-1');

        $this->travel((int) config('shop.return_window_days') + 1)->days();
        $this->assertSame(1, app(MembershipService::class)->recordDueSpend());

        $order->refresh();
        $handCalculated = $order->paid_amount - 40000 - ($order->shipping_fee - $order->shipping_discount);
        $this->assertGreaterThan(0, $handCalculated);
        $this->assertSame($handCalculated, $order->spend_contribution);
        $this->assertSame($handCalculated, $this->spendOf($user));
        $this->assertSame($handCalculated, $user->refresh()->lifetime_spend, 'lifetime_spend phải đúng bằng spendOf()');
    }
}

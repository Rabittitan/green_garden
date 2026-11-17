<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kiểm chứng schema đúng nghiệp vụ NGAY TẠI TẦNG DATABASE (không qua Model/Service):
 *  - các luồng hợp lệ ghi được
 *  - dữ liệu sai bị CHECK / UNIQUE / FK chặn, đúng tên ràng buộc
 */
class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public int $customer;
    public int $admin;
    public int $baseTier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = (int) DB::table('users')->where('role', 'customer')->value('id');
        $this->admin = (int) DB::table('users')->where('role', 'admin')->value('id');
        $this->baseTier = (int) DB::table('membership_tiers')->where('rank', 0)->value('id');
    }

    // ------------------------------------------------------------------ helpers

    public function order(array $override = []): int
    {
        return DB::table('orders')->insertGetId(array_merge([
            'code' => 'T'.Str::upper(Str::random(10)), 'user_id' => $this->customer, 'checkout_token' => (string) Str::uuid(),
            'status' => 'pending', 'recipient_name' => 'K', 'phone' => '0900000002', 'province' => 'HCM',
            'district' => 'Q1', 'ward' => 'BN', 'address_line' => '12 NH', 'membership_tier_id' => $this->baseTier,
            'subtotal' => 250000, 'shipping_fee' => 30000, 'total' => 280000, 'payment_method' => 'cod',
            'created_at' => now(), 'updated_at' => now(),
        ], $override));
    }

    public function item(int $orderId, array $override = []): int
    {
        return DB::table('order_items')->insertGetId(array_merge([
            'order_id' => $orderId, 'plant_id' => 1, 'plant_name' => 'Kim Tiền', 'plant_sku' => 'GL00001',
            'unit_price' => 250000, 'quantity' => 1, 'unit_cost' => 150000, 'created_at' => now(), 'updated_at' => now(),
        ], $override));
    }

    public function payment(int $orderId, array $override = []): int
    {
        return DB::table('payments')->insertGetId(array_merge([
            'order_id' => $orderId, 'type' => 'charge', 'method' => 'cod', 'amount' => 280000,
            'txn_ref' => 'P'.Str::upper(Str::random(12)), 'created_at' => now(), 'updated_at' => now(),
        ], $override));
    }

    private function assertBlocked(string $constraint, callable $write): void
    {
        try {
            DB::transaction(fn () => $write());
        } catch (QueryException $e) {
            $this->assertStringContainsString($constraint, $e->getMessage(), 'Bị chặn nhưng sai ràng buộc: '.$e->getMessage());

            return;
        }
        $this->fail("Dữ liệu sai KHÔNG bị chặn (mong đợi ràng buộc {$constraint}).");
    }

    // ------------------------------------------------------------------ seed

    public function test_seed_is_consistent(): void
    {
        $this->assertSame(4, DB::table('membership_tiers')->count());
        $this->assertSame(0, DB::table('users')->where('membership_tier_id', '<>', $this->baseTier)->count());
        $drift = DB::table('plants as p')
            ->whereRaw('p.stock <> (SELECT COALESCE(SUM(quantity),0) FROM stock_movements s WHERE s.plant_id = p.id)')
            ->count();
        $this->assertSame(0, $drift, 'plants.stock phải khớp sổ kho');
    }

    // ------------------------------------------------------------------ luồng hợp lệ

    public function test_cod_order_with_tier_and_coupon_goes_to_completed(): void
    {
        $gold = DB::table('membership_tiers')->where('code', 'gold')->value('id');
        $coupon = DB::table('coupons')->where('code', 'GIAM10')->value('id');
        // 500k, hạng Vàng 5% = 25k, mã 10% của 475k = 47.5k, miễn ship (Vàng >= 300k)
        $o = $this->order(['membership_tier_id' => $gold, 'member_discount_percent' => 5, 'coupon_id' => $coupon, 'coupon_code' => 'GIAM10',
            'subtotal' => 500000, 'member_discount' => 25000, 'coupon_discount' => 47500, 'shipping_fee' => 0, 'total' => 427500]);
        $i = $this->item($o, ['quantity' => 2, 'member_discount' => 25000, 'coupon_discount' => 47500]);
        DB::table('stock_movements')->insert(['plant_id' => 1, 'type' => 'sale', 'quantity' => -2, 'stock_after' => 28, 'unit_cost' => 150000, 'order_item_id' => $i]);
        DB::table('coupon_redemptions')->insert(['coupon_id' => $coupon, 'user_id' => $this->customer, 'order_id' => $o, 'discount_amount' => 47500]);
        $p = $this->payment($o, ['amount' => 427500]);

        DB::table('orders')->where('id', $o)->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        DB::table('orders')->where('id', $o)->update(['status' => 'shipping', 'shipping_carrier' => 'GHN', 'tracking_code' => 'GHN1', 'shipped_at' => now()]);
        DB::table('payments')->where('id', $p)->update(['status' => 'succeeded', 'paid_at' => now()]);
        DB::table('orders')->where('id', $o)->update(['status' => 'completed', 'completed_at' => now(),
            'payment_status' => 'paid', 'paid_amount' => 427500, 'paid_at' => now()]);
        DB::table('orders')->where('id', $o)->update(['spend_recorded_at' => now()]);

        $this->assertSame(500000, (int) DB::table('order_items')->where('id', $i)->value('line_subtotal'));
        $this->assertSame('completed', DB::table('orders')->where('id', $o)->value('status'));
    }

    public function test_free_shipping_on_order_smaller_than_shipping_fee(): void
    {
        $o = $this->order(['subtotal' => 20000, 'shipping_fee' => 30000, 'shipping_discount' => 30000, 'total' => 20000]);
        $this->assertSame(20000, (int) DB::table('orders')->where('id', $o)->value('total'));
    }

    public function test_online_payment_retry_then_late_ipn_after_expiry_goes_to_refund(): void
    {
        $o = $this->order(['status' => 'pending_payment', 'payment_method' => 'vnpay', 'payment_expires_at' => now()->addMinutes(15)]);
        $p1 = $this->payment($o, ['method' => 'vnpay']);
        DB::table('payments')->where('id', $p1)->update(['status' => 'failed']);
        $p2 = $this->payment($o, ['method' => 'vnpay']);                   // thanh toán lại

        DB::table('payments')->where('id', $p2)->update(['status' => 'cancelled']);
        DB::table('orders')->where('id', $o)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'Hết hạn']);
        DB::table('payments')->where('id', $p2)->update(['status' => 'succeeded', 'paid_at' => now(), 'provider_txn_no' => 'VNP1']); // IPN trễ
        $r = $this->payment($o, ['type' => 'refund', 'method' => 'vnpay', 'parent_payment_id' => $p2]);
        DB::table('orders')->where('id', $o)->update(['paid_amount' => 280000, 'paid_at' => now(), 'payment_status' => 'refund_pending']);
        DB::table('payments')->where('id', $r)->update(['status' => 'succeeded', 'paid_at' => now()]);
        DB::table('orders')->where('id', $o)->update(['refunded_amount' => 280000, 'payment_status' => 'refunded']);

        $this->assertSame('refunded', DB::table('orders')->where('id', $o)->value('payment_status'));
    }

    public function test_return_in_transit_with_damaged_plant(): void
    {
        $o = $this->order(['status' => 'shipping', 'tracking_code' => 'GHN2', 'shipped_at' => now()]);
        $i = $this->item($o);
        DB::table('orders')->where('id', $o)->update(['status' => 'returning']);
        DB::table('order_items')->where('id', $i)->update(['returned_damaged_qty' => 1]);
        DB::table('stock_movements')->insert([
            ['plant_id' => 1, 'type' => 'sale_return', 'quantity' => 1, 'stock_after' => 31, 'unit_cost' => 150000, 'order_item_id' => $i, 'note' => null],
            ['plant_id' => 1, 'type' => 'damage', 'quantity' => -1, 'stock_after' => 30, 'unit_cost' => 150000, 'order_item_id' => $i, 'note' => 'Cây dập khi hoàn'],
        ]);
        DB::table('orders')->where('id', $o)->update(['status' => 'returned', 'returned_at' => now()]);
        $this->assertSame('returned', DB::table('orders')->where('id', $o)->value('status'));
    }

    public function test_completed_order_can_be_partially_refunded_for_dead_plant_complaint(): void
    {
        $o = $this->order(['status' => 'completed', 'tracking_code' => 'GHN3', 'shipped_at' => now(), 'completed_at' => now(),
            'payment_status' => 'paid', 'paid_amount' => 280000, 'paid_at' => now()]);
        $c = $this->payment($o, ['status' => 'succeeded', 'paid_at' => now()]);
        $r = $this->payment($o, ['type' => 'refund', 'parent_payment_id' => $c, 'amount' => 100000]);
        DB::table('payments')->where('id', $r)->update(['status' => 'succeeded', 'paid_at' => now()]);
        DB::table('orders')->where('id', $o)->update(['refunded_amount' => 100000, 'payment_status' => 'partially_refunded']);
        $this->assertSame('partially_refunded', DB::table('orders')->where('id', $o)->value('payment_status'));
    }

    public function test_manual_tier_change_is_recorded(): void
    {
        $gold = DB::table('membership_tiers')->where('code', 'gold')->value('id');
        DB::table('users')->where('id', $this->customer)->update(['membership_tier_id' => $gold, 'tier_achieved_at' => now()]);
        DB::table('membership_tier_histories')->insert(['user_id' => $this->customer, 'from_tier_id' => $this->baseTier,
            'to_tier_id' => $gold, 'reason' => 'manual', 'changed_by' => $this->admin, 'note' => 'Khách sỉ']);
        $this->assertSame(2, DB::table('membership_tier_histories')->where('user_id', $this->customer)->count());
    }

    // ------------------------------------------------------------------ dữ liệu sai phải bị chặn

    public static function invalidWrites(): array
    {
        return [
            'đơn hủy mà vẫn paid' => ['chk_o_no_paid_on_closed', fn ($t) => $t->order(['status' => 'cancelled', 'cancelled_at' => now(),
                'payment_status' => 'paid', 'paid_amount' => 280000, 'paid_at' => now(), 'payment_method' => 'vnpay'])],
            'hoàn thành khi chưa thu tiền' => ['chk_o_completed_paid', fn ($t) => $t->order(['status' => 'completed', 'tracking_code' => 'X',
                'shipped_at' => now(), 'completed_at' => now()])],
            'tổng tiền sai công thức' => ['chk_o_total', fn ($t) => $t->order(['total' => 1])],
            'giảm giá vượt tiền hàng' => ['chk_o_item_disc', fn ($t) => $t->order(['member_discount' => 250001, 'total' => 0, 'shipping_fee' => 30001])],
            'giảm ship vượt phí ship' => ['chk_o_ship_disc', fn ($t) => $t->order(['shipping_discount' => 40000, 'shipping_fee' => 30000, 'total' => 240000])],
            'COD mà chờ thanh toán online' => ['chk_o_pending_payment', fn ($t) => $t->order(['status' => 'pending_payment', 'payment_expires_at' => now()])],
            'chờ thanh toán thiếu hạn' => ['chk_o_pending_payment', fn ($t) => $t->order(['status' => 'pending_payment', 'payment_method' => 'vnpay'])],
            'đang giao thiếu mã vận đơn' => ['chk_o_tracking', fn ($t) => $t->order(['status' => 'shipping', 'shipped_at' => now()])],
            'hủy thiếu cancelled_at' => ['chk_o_cancel', fn ($t) => $t->order(['status' => 'cancelled'])],
            'paid mà paid_amount khác total' => ['chk_o_ps_paid', fn ($t) => $t->order(['payment_status' => 'paid', 'paid_amount' => 1000, 'paid_at' => now()])],
            'hoàn tiền nhiều hơn đã thu' => ['chk_o_amounts', fn ($t) => $t->order(['payment_status' => 'refund_pending', 'paid_amount' => 1000,
                'refunded_amount' => 2000, 'paid_at' => now()])],
            'chốt chi tiêu cho đơn chưa hoàn thành' => ['chk_o_spend', fn ($t) => $t->order(['spend_recorded_at' => now()])],
            'trùng checkout_token' => ['uq_orders_checkout', function ($t) {
                $t->order(['checkout_token' => 'same-token']);
                $t->order(['checkout_token' => 'same-token']);
            }],
            'xóa chứng từ đơn hàng' => ['foreign key constraint fails', function ($t) {
                $o = $t->order();
                $t->item($o);
                DB::table('orders')->where('id', $o)->delete();
            }],
            'số hoàn > số mua' => ['chk_oi_return', fn ($t) => $t->item($t->order(), ['returned_good_qty' => 2])],
            'giảm dòng > thành tiền dòng' => ['chk_oi_disc', fn ($t) => $t->item($t->order(), ['coupon_discount' => 250001])],
            '2 charge thành công / 1 đơn' => ['uq_pay_success_charge', function ($t) {
                $o = $t->order();
                $t->payment($o, ['status' => 'succeeded', 'paid_at' => now()]);
                $t->payment($o, ['status' => 'succeeded', 'paid_at' => now()]);
            }],
            '2 charge đang chờ / 1 đơn' => ['uq_pay_pending_charge', function ($t) {
                $o = $t->order();
                $t->payment($o);
                $t->payment($o);
            }],
            'refund không có charge gốc' => ['chk_pay_parent', fn ($t) => $t->payment($t->order(), ['type' => 'refund'])],
            'succeeded thiếu paid_at' => ['chk_pay_paid_at', fn ($t) => $t->payment($t->order(), ['status' => 'succeeded'])],
            'số tiền thanh toán = 0' => ['chk_pay_amount', fn ($t) => $t->payment($t->order(), ['amount' => 0])],
            'bán mà cộng kho' => ['chk_sm_sign', fn ($t) => DB::table('stock_movements')->insert(['plant_id' => 1, 'type' => 'sale',
                'quantity' => 1, 'stock_after' => 31, 'order_item_id' => $t->item($t->order())])],
            'bán không gắn dòng đơn' => ['chk_sm_ref', fn ($t) => DB::table('stock_movements')->insert(['plant_id' => 1, 'type' => 'sale',
                'quantity' => -1, 'stock_after' => 29])],
            'tồn kho âm' => ['out of range', fn ($t) => DB::table('plants')->where('id', 1)->update(['stock' => DB::raw('stock - 1000')])],
            '2 địa chỉ mặc định' => ['uq_addr_default', fn ($t) => DB::table('user_addresses')->insert(['user_id' => $t->customer,
                'recipient_name' => 'K', 'phone' => '0', 'province' => 'a', 'district' => 'b', 'ward' => 'c', 'address_line' => 'd', 'is_default' => true])],
            'hạng giảm > 50%' => ['chk_tier_percent', fn ($t) => DB::table('membership_tiers')->insert(['code' => 'vip', 'name' => 'VIP',
                'rank' => 9, 'min_spend' => 99000000, 'discount_percent' => 60])],
            'hạng cơ bản phải min_spend = 0' => ['chk_tier_base', fn ($t) => DB::table('membership_tiers')->where('rank', 0)->update(['min_spend' => 1])],
            'đổi hạng tay không ghi người đổi' => ['chk_mth_manual', fn ($t) => DB::table('membership_tier_histories')->insert([
                'user_id' => $t->customer, 'to_tier_id' => $t->baseTier, 'reason' => 'manual'])],
            'mã freeship theo cây' => ['chk_coupon_freeship_scope', fn ($t) => DB::table('coupons')->insert(['code' => 'FSX', 'name' => 'x',
                'type' => 'free_shipping', 'scope' => 'plants'])],
            'mã percent = 0' => ['chk_coupon_value', fn ($t) => DB::table('coupons')->insert(['code' => 'P0', 'name' => 'x', 'type' => 'percent', 'value' => 0])],
            'mã dùng quá giới hạn' => ['chk_coupon_usage', fn ($t) => DB::table('coupons')->insert(['code' => 'U1', 'name' => 'x', 'type' => 'fixed',
                'value' => 1000, 'usage_limit' => 1, 'used_count' => 2])],
            'người tạo tự duyệt yêu cầu nhập' => ['chk_pr_4eyes', fn ($t) => DB::table('purchase_requests')->insert(['code' => 'YCN1',
                'requested_by' => $t->admin, 'approved_by' => $t->admin, 'status' => 'approved'])],
        ];
    }

    #[DataProvider('invalidWrites')]
    public function test_invalid_data_is_rejected_by_database(string $constraint, \Closure $write): void
    {
        $this->assertBlocked($constraint, fn () => $write($this));
    }
}

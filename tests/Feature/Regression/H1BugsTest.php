<?php

namespace Tests\Feature\Regression;

use App\Enums\TierChangeReason;
use App\Events\OrderPlaced;
use App\Mail\OrderPlacedMail;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\PaymentWebhookLog;
use App\Services\MembershipService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Hồi quy cho các lỗi B1-B3, B6, B8 phát hiện khi rà soát (H1). B7 nằm ở ConcurrencyTest. */
class H1BugsTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function completeAndRecord(array $cart): Order
    {
        $orders = app(OrderService::class);
        $order = $this->place(cart: $cart);
        $orders->confirm($order, $this->admin());
        $orders->ship($order, 'GHN', 'T'.$order->id, $this->admin());
        $orders->complete($order, $this->admin());
        $this->travel(8)->days();
        app(MembershipService::class)->recordDueSpend();

        return $order->refresh();
    }

    /** B1: admin hạ hạng thủ công không được bị hệ thống tự nâng lại ở lần chốt chi tiêu kế tiếp. */
    public function test_b1_manual_downgrade_is_respected_until_customer_crosses_a_new_threshold(): void
    {
        $this->completeAndRecord([1 => 13]);                                   // 3.25tr -> Bạc
        $this->assertSame('silver', $this->customer()->tier->code);

        $member = MembershipTier::where('code', 'member')->first();
        app(MembershipService::class)->setManual($this->customer(), $member, $this->admin(), 'Nghi gian lận');

        $this->completeAndRecord([5 => 2]);                                   // thêm 90k: vẫn trong khoảng Bạc
        $this->assertSame('member', $this->customer()->tier->code, 'Hạ hạng thủ công bị tự nâng lại');

        $this->completeAndRecord([6 => 15]);                                  // +6.75tr Bàng Singapore: tổng 10.09tr, vượt ngưỡng Vàng
        $user = $this->customer();
        $this->assertSame('gold', $user->tier->code);
        $this->assertSame(TierChangeReason::Upgrade, $user->tierHistories()->first()->reason);
    }

    /** B1 (chiều ngược): nâng hạng thủ công lên cao hơn mức chi tiêu thì giữ nguyên, không bị hạ. */
    public function test_b1_manual_upgrade_is_kept(): void
    {
        $diamond = MembershipTier::where('code', 'diamond')->first();
        app(MembershipService::class)->setManual($this->customer(), $diamond, $this->admin(), 'Đối tác');
        $this->completeAndRecord([1 => 13]);
        $this->assertSame('diamond', $this->customer()->tier->code);
    }

    /** B2: mail xác nhận phải gửi đúng tên người nhận (cột customer_name đã bỏ). */
    public function test_b2_order_mail_uses_recipient_name(): void
    {
        Mail::fake();
        OrderPlaced::dispatch($this->place());
        Mail::assertSent(OrderPlacedMail::class, fn ($m) => $m->hasTo('khach@greenlife.test', 'Nguyễn Văn Khách'));
    }

    /** B3: hạ ngưỡng hạng thì khách đủ điều kiện được nâng ngay, không phải chờ đơn mới. */
    public function test_b3_lowering_threshold_upgrades_eligible_customers_immediately(): void
    {
        $this->completeAndRecord([1 => 1]);                                   // 250k
        $this->assertSame('member', $this->customer()->tier->code);

        $tiers = MembershipTier::ordered()->get()->mapWithKeys(fn ($t) => [$t->id => [
            'name' => $t->name, 'min_spend' => $t->code === 'silver' ? 200000 : $t->min_spend, 'discount_percent' => $t->discount_percent,
            'max_discount_per_order' => $t->max_discount_per_order, 'free_shipping_min_order' => $t->free_shipping_min_order,
        ]])->all();
        $this->actingAs($this->admin())->put(route('admin.tiers.update'), ['tiers' => $tiers])->assertSessionHas('success');

        $this->assertSame('silver', $this->customer()->tier->code);
        $this->artisan('membership:recalculate-all')->assertSuccessful();
    }

    /** B6: doanh thu trên dashboard phải trừ phần đã hoàn của đơn hoàn thành. */
    public function test_b6_dashboard_revenue_is_net_of_refunds(): void
    {
        $order = $this->completeAndRecord([1 => 1]);                          // doanh thu hàng 250k
        $payments = app(PaymentService::class);
        $payments->confirmRefund($payments->requestRefund($order, 100000, $this->admin(), 'Cây chết'), $this->admin(), 'RF');

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('stats', fn (array $s) => $s['cur']['revenue'] === 150000);   // thẻ doanh thu kỳ này
    }

    /** B8: log callback thanh toán cũ hơn 180 ngày được dọn, log mới giữ lại. */
    public function test_b8_old_webhook_logs_are_pruned(): void
    {
        PaymentWebhookLog::create(['provider' => 'vnpay', 'payload' => [], 'signature_valid' => true]);
        $this->travel(181)->days();
        PaymentWebhookLog::create(['provider' => 'vnpay', 'payload' => [], 'signature_valid' => true]);

        $this->artisan('payments:prune-webhook-logs')->assertSuccessful();
        $this->assertSame(1, PaymentWebhookLog::count());
    }
}

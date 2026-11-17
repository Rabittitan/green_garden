<?php

namespace Tests\Feature\Services;

use App\Enums\ActorType;
use App\Enums\StockMovementType;
use App\Enums\TierChangeReason;
use App\Exceptions\BusinessException;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\Plant;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\MembershipService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class MembershipAndInventoryTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function completedOrder(array $cart): Order
    {
        $orders = app(OrderService::class);
        $order = $this->place(cart: $cart);
        $orders->confirm($order, $this->admin());
        $orders->ship($order, 'GHN', 'G'.$order->id, $this->admin());

        return $orders->complete($order, $this->admin())->refresh();
    }

    // ------------------------------------------------------------- hạng thành viên

    public function test_spend_counts_only_after_return_window_and_upgrades_tier(): void
    {
        $order = $this->completedOrder([1 => 13]);                          // 3.250.000đ, miễn ship
        $membership = app(MembershipService::class);

        $this->assertSame(0, $membership->recordDueSpend());               // còn trong hạn đổi trả
        $this->assertSame('member', $this->customer()->tier->code);

        $this->travel(7)->days();
        $this->assertSame(1, $membership->recordDueSpend());
        $user = $this->customer();
        $this->assertSame(3250000, $user->lifetime_spend);
        $this->assertSame('silver', $user->tier->code);
        $history = $user->tierHistories()->first();
        $this->assertSame([TierChangeReason::Upgrade, $order->id], [$history->reason, $history->order_id]);
        $this->assertSame(0, $membership->recordDueSpend());               // không chốt 2 lần

        // đã chốt chi tiêu thì không còn trả hàng được (DB cũng chặn bằng chk_o_spend)
        $this->expectException(BusinessException::class);
        app(OrderService::class)->markReturning($order, ActorType::Staff, $this->admin(), 'muộn');
    }

    public function test_shipping_is_excluded_and_refund_reduces_spend_but_never_downgrades(): void
    {
        $order = $this->completedOrder([1 => 1]);                           // 250k + 30k ship
        $this->travel(8)->days();
        app(MembershipService::class)->recordDueSpend();
        $this->assertSame(250000, $this->customer()->lifetime_spend);      // không tính phí ship

        $this->withTier($this->customer(), 'gold');
        $payments = app(PaymentService::class);
        $refund = $payments->requestRefund($order, 280000, $this->admin(), 'Hoàn toàn bộ (cả ship)');
        $payments->confirmRefund($refund, $this->admin(), 'RF1');

        $user = $this->customer();
        $this->assertSame(0, $user->lifetime_spend);                       // GREATEST(0, ...) - không tràn số UNSIGNED
        $this->assertSame('gold', $user->tier->code);                       // hạng không tự hạ
    }

    public function test_manual_tier_change_requires_reason(): void
    {
        $membership = app(MembershipService::class);
        $gold = MembershipTier::where('code', 'gold')->first();
        try {
            $membership->setManual($this->customer(), $gold, $this->admin(), ' ');
            $this->fail();
        } catch (BusinessException) {
        }
        $membership->setManual($this->customer(), $gold, $this->admin(), 'Khách sỉ');
        $history = $this->customer()->tierHistories()->first();
        $this->assertSame([TierChangeReason::Manual, $this->admin()->id], [$history->reason, $history->changed_by]);
    }

    // ------------------------------------------------------------- kho

    public function test_stock_count_uses_locked_current_stock_not_stale_form_value(): void
    {
        $inventory = app(InventoryService::class);
        $this->place(cart: [1 => 5]);                                       // 30 -> 25 trong lúc admin đi đếm

        $m = $inventory->countTo(1, 24, 'Kiểm kê cuối tháng', $this->admin()->id);
        $this->assertSame([StockMovementType::Adjustment, -1, 24], [$m->type, $m->quantity, $m->stock_after]);
        $this->assertNull($inventory->countTo(1, 24, 'Đếm lại'));          // không chênh lệch -> không ghi
        $this->assertLedgerConsistent();
    }

    public function test_damage_and_opening_stock(): void
    {
        $inventory = app(InventoryService::class);
        $inventory->recordDamage(1, 2, 'Cây chết do nắng');
        $this->assertSame(28, $this->stock(1));
        try {
            $inventory->recordDamage(16, 5, 'quá số tồn');
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('không đủ hàng', $e->getMessage());
        }

        $plant = Plant::create(['category_id' => 1, 'sku' => 'NEW01', 'name' => 'Cây mới', 'slug' => 'cay-moi',
            'price' => 100000, 'light' => 'ua_bong', 'element' => 'moc']);
        $inventory->opening($plant, 10, 60000);
        $this->assertSame([10, 60000], [$plant->fresh()->stock, $plant->fresh()->cost_price]);
        $this->assertSame(StockMovementType::Opening, StockMovement::where('plant_id', $plant->id)->sole()->type);
        $this->assertLedgerConsistent();
    }
}

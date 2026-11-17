<?php

namespace Tests\Feature\Services;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\RedemptionStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = app(OrderService::class);
    }

    private function deliver(Order $order): Order
    {
        $this->orders->confirm($order, $this->admin());
        $this->orders->ship($order, 'GHN', 'GHN123', $this->admin());

        return $this->orders->complete($order, $this->admin())->refresh();
    }

    public function test_cod_happy_path_collects_money_on_completion(): void
    {
        $order = $this->deliver($this->place());

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(280000, $order->paid_amount);
        $this->assertSame(PaymentState::Succeeded, $order->payments()->sole()->status);
        $this->assertSame(['pending', 'confirmed', 'shipping', 'completed'],
            $order->histories()->get()->map(fn ($h) => $h->to_status->value)->all());
    }

    public function test_illegal_transitions_and_missing_tracking_are_rejected(): void
    {
        $order = $this->place();
        try {
            $this->orders->ship($order, 'GHN', 'X', $this->admin());          // chưa xác nhận
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('Không thể chuyển', $e->getMessage());
        }
        $this->orders->confirm($order, $this->admin());
        $this->expectExceptionMessage('mã vận đơn');
        $this->orders->ship($order, 'GHN', ' ', $this->admin());
    }

    public function test_cancel_restores_stock_at_original_cost_and_releases_coupon(): void
    {
        $order = $this->place(cart: [1 => 2], coupon: 'CHAOBAN');
        Plant::writingLedger(function () {                                  // giá vốn đổi sau khi bán
            $p = Plant::find(1);
            $p->cost_price = 999999;
            $p->save();
        });

        $order = $this->orders->cancel($order, ActorType::Customer, $this->customer(), 'Đổi ý')->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Đổi ý', $order->cancel_reason);
        $this->assertSame(30, $this->stock(1));
        $cancel = StockMovement::where('type', StockMovementType::SaleCancel->value)->sole();
        $this->assertSame(150000, $cancel->unit_cost);                      // giá vốn LÚC BÁN, không phải 999999
        $this->assertSame(RedemptionStatus::Released, $order->redemption->status);
        $this->assertSame(0, Coupon::where('code', 'CHAOBAN')->value('used_count'));
        $this->assertSame(PaymentState::Cancelled, $order->payments()->sole()->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertLedgerConsistent();

        $this->place(cart: [1 => 2], coupon: 'CHAOBAN');                   // trả lượt -> dùng lại được
    }

    public function test_customer_cannot_cancel_confirmed_or_other_peoples_orders(): void
    {
        $order = $this->place();
        $stranger = User::factory()->create();
        try {
            $this->orders->cancel($order, ActorType::Customer, $stranger, 'x');
            $this->fail();
        } catch (BusinessException) {
        }
        $this->orders->confirm($order, $this->admin());
        $this->expectException(BusinessException::class);
        $this->orders->cancel($order, ActorType::Customer, $this->customer(), 'x');
    }

    public function test_cancelling_a_paid_order_creates_refund(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->assertSame('00', $this->ipn($order->payments()->sole()));
        $order = $this->orders->cancel($order->refresh(), ActorType::Staff, $this->admin(), 'Hết hàng thật')->refresh();

        $this->assertSame(PaymentStatus::RefundPending, $order->payment_status);
        $refund = $order->refunds()->sole();
        $this->assertSame([280000, PaymentState::Pending], [$refund->amount, $refund->status]);
    }

    public function test_return_in_transit_restocks_good_plants_and_writes_off_damaged(): void
    {
        $order = $this->place(cart: [1 => 3]);
        $this->orders->confirm($order, $this->admin());
        $this->orders->ship($order, 'GHN', 'GHN9', $this->admin());
        $this->assertSame(27, $this->stock(1));                            // đang giao: KHÔNG cộng kho
        $this->orders->markReturning($order, ActorType::Staff, $this->admin(), 'Khách không nhận');
        $this->assertSame(27, $this->stock(1));                            // đang hoàn về: vẫn chưa cộng

        $item = $order->items()->sole();
        $order = $this->orders->receiveReturn($order, [$item->id => ['good' => 2, 'damaged' => 1]], $this->admin())->refresh();

        $this->assertSame(OrderStatus::Returned, $order->status);
        $this->assertSame(29, $this->stock(1));                            // 27 + 3 về - 1 hỏng
        $this->assertSame(['sale', 'sale_return', 'damage'],
            StockMovement::where('order_item_id', $item->id)->orderBy('id')->pluck('type')->map->value->all());
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);   // COD chưa thu -> không hoàn gì
        $this->assertSame(PaymentState::Cancelled, $order->payments()->sole()->status);
        $this->assertLedgerConsistent();
    }

    public function test_return_quantities_must_cover_whole_line(): void
    {
        $order = $this->place(cart: [1 => 3]);
        $this->orders->confirm($order, $this->admin());
        $this->orders->ship($order, 'GHN', 'GHN9', $this->admin());
        $this->orders->markReturning($order, ActorType::Staff, $this->admin(), 'x');
        $this->expectExceptionMessage('phải bằng 3');
        $this->orders->receiveReturn($order, [$order->items()->sole()->id => ['good' => 1, 'damaged' => 1]], $this->admin());
    }

    public function test_completed_order_returned_within_window_is_refunded(): void
    {
        $order = $this->deliver($this->place(cart: [1 => 1]));
        $order = $this->orders->markReturning($order, ActorType::Customer, $this->customer(), 'Cây héo')->refresh();
        $item = $order->items()->sole();
        $order = $this->orders->receiveReturn($order, [$item->id => ['good' => 0, 'damaged' => 1]], $this->admin(),
            refundAmount: 250000, note: 'Không hoàn phí ship')->refresh();

        $this->assertSame(PaymentStatus::RefundPending, $order->payment_status);
        $this->assertSame(250000, $order->refunds()->sole()->amount);
    }

    public function test_return_after_window_is_rejected(): void
    {
        $order = $this->deliver($this->place());
        $this->travel(8)->days();
        $this->expectExceptionMessage('quá hạn đổi trả');
        $this->orders->markReturning($order, ActorType::Customer, $this->customer(), 'muộn');
    }

    public function test_expired_unpaid_orders_are_cancelled_and_restocked(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->assertSame(0, $this->orders->expireOverdue());               // chưa hết hạn

        $this->travel(18)->minutes();                                      // 15' giữ hàng + 2' ân hạn
        $this->assertSame(1, $this->orders->expireOverdue());
        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Hết hạn thanh toán', $order->cancel_reason);
        $this->assertSame(30, $this->stock(1));
        $this->assertSame(ActorType::System, $order->histories()->get()->last()->actor_type);
        $this->assertSame(0, $this->orders->expireOverdue());               // chạy lại: không làm gì
    }

    public function test_system_cannot_cancel_non_expired_order(): void
    {
        $this->expectException(BusinessException::class);
        $this->orders->cancel($this->place(method: PaymentMethod::Vnpay), ActorType::System, null, 'x');
    }
}

<?php

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Exceptions\CouponNotApplicableException;
use App\Exceptions\PriceChangedException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plant;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_cod_order_writes_every_record_consistently(): void
    {
        $order = $this->place(cart: [1 => 2, 3 => 1]);                      // 500k + 120k -> miễn ship

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame([620000, 0, 620000], [$order->subtotal, $order->shipping_fee, $order->total]);
        $this->assertSame('Phường Bến Nghé', $order->ward);
        $this->assertSame(28, $this->stock(1));
        $this->assertSame(49, $this->stock(3));

        $item = $order->items->firstWhere('plant_id', 1);
        $this->assertSame([250000, 150000, 'Cây Kim Tiền'], [$item->unit_price, $item->unit_cost, $item->plant_name]);
        $sale = StockMovement::where('order_item_id', $item->id)->sole();
        $this->assertSame([StockMovementType::Sale, -2, 28, 150000], [$sale->type, $sale->quantity, $sale->stock_after, $sale->unit_cost]);

        $charge = $order->payments()->sole();
        $this->assertSame([PaymentMethod::Cod, PaymentState::Pending, 620000], [$charge->method, $charge->status, $charge->amount]);
        $this->assertSame(OrderStatus::Pending, $order->histories()->sole()->to_status);
        $this->assertLedgerConsistent();
    }

    public function test_vnpay_order_holds_stock_until_expiry(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, $order->payment_expires_at->timestamp, 5);
        $this->assertSame(29, $this->stock(1));
        $charge = $order->payments()->sole();
        $this->assertSame('127.0.0.1', $charge->raw_payload['init']['ip']);
        $this->assertSame($order->code.'-1', $charge->txn_ref);
    }

    public function test_bank_transfer_holds_24h(): void
    {
        $order = $this->place(method: PaymentMethod::BankTransfer);
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $order->payment_expires_at->timestamp, 5);
    }

    public function test_zero_total_prepaid_order_skips_gateway(): void
    {
        $user = $this->withTier($this->customer(), 'diamond');               // kim cương: miễn ship mọi đơn
        Coupon::create(['code' => 'FULL', 'name' => 'x', 'type' => 'fixed', 'value' => 10_000_000]);
        $order = $this->place($user, [5 => 1], PaymentMethod::Vnpay, 'FULL');

        $this->assertSame(0, $order->total);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_double_submit_with_same_token_creates_one_order(): void
    {
        $a = $this->place(token: 'tok-1');
        $b = $this->place(token: 'tok-1');

        $this->assertTrue($a->is($b));
        $this->assertSame(1, Order::count());
        $this->assertSame(29, $this->stock(1));
    }

    public function test_price_change_between_preview_and_submit_is_rejected(): void
    {
        Plant::find(1)->update(['price' => 260000]);
        try {
            $this->place(expectedTotal: 280000);
            $this->fail('Phải báo giá đã đổi');
        } catch (PriceChangedException $e) {
            $this->assertSame(290000, $e->quote->total());
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(30, $this->stock(1));

        $this->assertSame(290000, $this->place(expectedTotal: 290000)->total);
    }

    public function test_insufficient_or_inactive_stock_writes_nothing(): void
    {
        try {
            $this->place(cart: [1 => 1, 16 => 2]);                          // cây #16 chỉ còn 1
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('chỉ còn 1', $e->getMessage());
        }
        $this->assertSame(30, $this->stock(1));                            // dòng hợp lệ cũng không bị trừ

        Plant::find(3)->update(['is_active' => false]);
        $this->expectExceptionMessage('ngừng bán');
        $this->place(cart: [3 => 1]);
    }

    public function test_limit_of_pending_payment_orders_per_customer(): void
    {
        foreach (range(1, 3) as $i) {
            $this->place(method: PaymentMethod::Vnpay);
        }
        $this->expectExceptionMessage('quá nhiều đơn chờ thanh toán');
        $this->place(method: PaymentMethod::Vnpay);
    }

    public function test_coupon_is_redeemed_and_invalid_coupon_blocks_order(): void
    {
        $order = $this->place(cart: [1 => 2], coupon: 'chaoban');
        $this->assertSame(['CHAOBAN', 50000, 480000], [$order->coupon_code, $order->coupon_discount, $order->total]);
        $this->assertSame(1, Coupon::where('code', 'CHAOBAN')->value('used_count'));
        $this->assertSame(50000, $order->redemption->discount_amount);
        $this->assertSame(50000, $order->items->sum('coupon_discount'));

        $this->expectException(CouponNotApplicableException::class);    // đơn thứ 2: không còn là đơn đầu
        $this->place(cart: [1 => 2], coupon: 'CHAOBAN');
    }

    public function test_member_discount_snapshot_on_order(): void
    {
        $user = $this->withTier($this->customer(), 'gold');
        $order = $this->place($user, [1 => 2]);
        $this->assertSame([5, 25000, 475000], [$order->member_discount_percent, $order->member_discount, $order->total]);
        $this->assertSame('gold', $order->tier->code);
    }
}

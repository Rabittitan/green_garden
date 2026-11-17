<?php

namespace Tests\Feature\Audit;

use App\Enums\ActorType;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PurchaseRequestStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\MembershipTierHistory;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Plant;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * H5 — Gây lỗi ở BƯỚC CUỐI của từng luồng: mọi thứ đã ghi trước đó phải được rollback sạch
 * (kho, tiền, mã, hạng), rồi chạy lại phải thành công. Lỗi ném ra là lỗi hệ thống (không phải nghiệp vụ).
 */
class FailureInjectionTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private bool $armed = true;

    /** Gắn "bẫy" vào sự kiện model: lần đầu chạm tới thì nổ. */
    private function trap(string $model, string $event): void
    {
        $this->armed = true;
        $model::{$event}(function () {
            if ($this->armed) {
                $this->armed = false;
                throw new RuntimeException('Lỗi giả lập giữa chừng');
            }
        });
    }

    private function assertBoom(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Lẽ ra phải nổ');
        } catch (RuntimeException $e) {
            $this->assertSame('Lỗi giả lập giữa chừng', $e->getMessage());
        }
    }

    private function assertConsistent(): void
    {
        $this->assertSame(0, Artisan::call('shop:reconcile'), Artisan::output());
    }

    public function test_placing_an_order_rolls_back_everything_then_succeeds_on_retry(): void
    {
        $this->trap(Payment::class, 'creating');                               // nổ ở bước gần cuối (mở khoản thu)
        $this->assertBoom(fn () => $this->place(cart: [1 => 2], coupon: 'CHAOBAN', token: 'tok'));

        $this->assertSame([0, 30, 0, 0, 0], [Order::count(), $this->stock(1), Coupon::where('code', 'CHAOBAN')->value('used_count'),
            CouponRedemption::count(), StockMovement::where('type', 'sale')->count()]);
        $this->assertConsistent();

        $order = $this->place(cart: [1 => 2], coupon: 'CHAOBAN', token: 'tok');  // cùng token: đặt lại được
        $this->assertSame(28, $this->stock(1));
        $this->assertSame('CHAOBAN', $order->coupon_code);
    }

    public function test_cancel_rolls_back_stock_coupon_and_payment(): void
    {
        $order = $this->place(cart: [1 => 2], coupon: 'CHAOBAN');
        $this->trap(OrderStatusHistory::class, 'creating');                     // nổ ở bước CUỐI của hủy đơn
        $this->assertBoom(fn () => app(OrderService::class)->cancel($order, ActorType::Staff, $this->admin(), 'x'));

        $order->refresh();
        $this->assertSame([OrderStatus::Pending, 28, 1, PaymentState::Pending], [$order->status, $this->stock(1),
            Coupon::where('code', 'CHAOBAN')->value('used_count'), $order->payments()->sole()->status]);
        $this->assertConsistent();
        app(OrderService::class)->cancel($order, ActorType::Staff, $this->admin(), 'x');
        $this->assertSame(30, $this->stock(1));
    }

    public function test_ipn_failure_keeps_raw_log_and_vnpay_retry_succeeds(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $charge = $order->payments()->sole();
        $this->trap(OrderStatusHistory::class, 'creating');
        $this->assertBoom(fn () => $this->ipn($charge));

        $this->assertSame([OrderStatus::PendingPayment, PaymentState::Pending], [$order->refresh()->status, $charge->refresh()->status]);
        $this->assertSame(1, PaymentWebhookLog::count(), 'Log thô phải còn để tra soát');
        $this->assertSame(PaymentService::IPN_OK, $this->ipn($charge));       // VNPay gọi lại
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertConsistent();
    }

    public function test_goods_receipt_completion_rolls_back_stock_and_cost(): void
    {
        $purchases = app(PurchaseService::class);
        $staff = User::factory()->staff()->create();
        $pr = $purchases->createRequest($staff, [1 => ['quantity' => 10, 'estimated_unit_cost' => 1]]);
        $purchases->transition($pr, PurchaseRequestStatus::Submitted, $staff);
        $purchases->approve($pr, $this->admin());
        $gr = $purchases->createReceipt($staff, Supplier::first(), $pr->refresh(), [1 => ['quantity_received' => 10, 'unit_cost' => 300000]]);

        $this->trap(PurchaseRequest::class, 'updating');                        // nổ khi cập nhật trạng thái yêu cầu (bước cuối)
        $this->assertBoom(fn () => $purchases->completeReceipt($gr));
        $this->assertSame([30, 150000, GoodsReceiptStatus::Draft, 0], [$this->stock(1), Plant::find(1)->cost_price,
            $gr->refresh()->status, $pr->items()->sole()->quantity_received]);
        $this->assertConsistent();
        $purchases->completeReceipt($gr);
        $this->assertSame(40, $this->stock(1));
    }

    /** F3: 1 khách lỗi không được chặn cả lô chốt chi tiêu (job chạy mỗi giờ sẽ kẹt mãi ở đơn đó). */
    public function test_spend_recording_isolates_a_failing_customer_and_rolls_it_back(): void
    {
        $orders = app(OrderService::class);
        $other = User::factory()->create();
        foreach ([$this->customer(), $other] as $u) {
            $o = $this->place($u, [1 => 13]);
            $orders->confirm($o, $this->admin());
            $orders->ship($o, 'GHN', 'F'.$o->id, $this->admin());
            $orders->complete($o, $this->admin());
        }
        $this->travel(8)->days();

        $this->trap(MembershipTierHistory::class, 'creating');                  // nổ khi ghi lịch sử lên hạng của khách ĐẦU TIÊN
        $this->assertSame(1, app(MembershipService::class)->recordDueSpend(), 'Khách thứ 2 vẫn phải được chốt');
        $first = $this->customer();
        $this->assertSame([0, 'member'], [$first->lifetime_spend, $first->tier->code]);   // khách lỗi: rollback sạch
        $this->assertSame('silver', $other->refresh()->tier->code);

        $this->assertSame(1, app(MembershipService::class)->recordDueSpend());   // lần chạy sau xử lý nốt
        $this->assertSame('silver', $this->customer()->tier->code);
        $this->assertConsistent();
    }

    public function test_refund_confirmation_rolls_back(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->ipn($order->payments()->sole());
        app(OrderService::class)->cancel($order->refresh(), ActorType::Staff, $this->admin(), 'x');
        $refund = $order->refunds()->sole();

        $this->trap(Order::class, 'updating');
        $this->assertBoom(fn () => app(PaymentService::class)->confirmRefund($refund, $this->admin(), 'RF'));
        $this->assertSame([PaymentState::Pending, 0], [$refund->refresh()->status, $order->refresh()->refunded_amount]);
        $this->assertConsistent();
    }
}

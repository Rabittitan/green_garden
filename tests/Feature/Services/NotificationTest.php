<?php

namespace Tests\Feature\Services;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\MembershipTier;
use App\Notifications\LatePaymentRefund;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderCompleted;
use App\Notifications\OrderShipped;
use App\Notifications\PaymentReceived;
use App\Notifications\RefundCompleted;
use App\Notifications\ReturnInstructions;
use App\Notifications\TierUpgraded;
use App\Services\MembershipService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\Notifier;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** H2: mỗi mốc tiền / giao hàng / hạng có email, chỉ gửi sau commit, lỗi gửi không phá nghiệp vụ. */
class NotificationTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->orders = app(OrderService::class);
    }

    public function test_payment_shipping_cancel_and_refund_emails(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->ipn($order->payments()->sole());
        Notification::assertSentTo($this->customer(), PaymentReceived::class, fn ($n) => $n->order->is($order));

        $this->orders->cancel($order->refresh(), ActorType::Staff, $this->admin(), 'Hết hàng');
        Notification::assertSentTo($this->customer(), OrderCancelled::class, function ($n) {
            return str_contains(implode(' ', $n->toMail($this->customer())->introLines), 'hoàn lại 280.000đ');
        });

        $refund = $order->refunds()->sole();
        app(PaymentService::class)->confirmRefund($refund, $this->admin(), 'RF1');
        Notification::assertSentTo($this->customer(), RefundCompleted::class);

        $cod = $this->place();
        $this->orders->confirm($cod, $this->admin());
        $this->orders->ship($cod, 'GHN', 'G-1', $this->admin());
        Notification::assertSentTo($this->customer(), OrderShipped::class, fn ($n) => $n->order->is($cod));
    }

    /** H8 (C9): COD giao xong = mốc thu tiền + bắt đầu hạn đổi trả -> phải có email. */
    public function test_completed_cod_order_emails_cash_receipt_and_return_deadline(): void
    {
        $cod = $this->place();
        $this->orders->confirm($cod, $this->admin());
        $this->orders->ship($cod, 'GHN', 'G-2', $this->admin());
        $this->orders->complete($cod, $this->admin());
        Notification::assertSentTo($this->customer(), OrderCompleted::class, function ($n) use ($cod) {
            $text = implode(' ', $n->toMail($this->customer())->introLines);

            return $n->order->is($cod) && str_contains($text, '280.000đ tiền mặt') && str_contains($text, 'yêu cầu trả hàng');
        });
    }

    /** H8 (C9): tiền về sau khi đơn đã hết hạn -> shop giữ tiền khách, phải báo sẽ hoàn. */
    public function test_late_payment_on_cancelled_order_tells_customer_about_refund(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $charge = $order->payments()->sole();
        $this->travel(30)->minutes();
        $this->orders->expireOverdue();
        Notification::assertNotSentTo($this->customer(), LatePaymentRefund::class);

        $this->ipn($charge);
        Notification::assertSentTo($this->customer(), LatePaymentRefund::class, fn ($n) => $n->amount === 280000
            && str_contains(implode(' ', $n->toMail($this->customer())->introLines), 'hoàn lại toàn bộ'));
        Notification::assertNotSentTo($this->customer(), PaymentReceived::class);
    }

    public function test_return_instructions_only_when_customer_holds_the_plants(): void
    {
        $failed = $this->place();
        $this->orders->confirm($failed, $this->admin());
        $this->orders->ship($failed, 'GHN', 'F1', $this->admin());
        $this->orders->markReturning($failed, ActorType::Staff, $this->admin(), 'Giao thất bại');
        Notification::assertNotSentTo($this->customer(), ReturnInstructions::class);

        $done = $this->place();
        $this->orders->confirm($done, $this->admin());
        $this->orders->ship($done, 'GHN', 'D1', $this->admin());
        $this->orders->complete($done, $this->admin());
        $this->orders->markReturning($done, ActorType::Customer, $this->customer(), 'Cây héo');
        Notification::assertSentTo($this->customer(), ReturnInstructions::class);
        $this->actingAs($this->customer())->get(route('orders.show', $done))->assertSee('Hướng dẫn trả hàng');
    }

    public function test_tier_upgrade_email_on_upgrade_but_not_on_downgrade(): void
    {
        $membership = app(MembershipService::class);
        $membership->setManual($this->customer(), MembershipTier::where('code', 'gold')->first(), $this->admin(), 'VIP');
        Notification::assertSentToTimes($this->customer(), TierUpgraded::class, 1);
        $membership->setManual($this->customer(), MembershipTier::where('code', 'member')->first(), $this->admin(), 'Hạ');
        Notification::assertSentToTimes($this->customer(), TierUpgraded::class, 1);
    }

    public function test_nothing_is_sent_when_the_transaction_rolls_back(): void
    {
        try {
            DB::transaction(function () {
                Notifier::send($this->customer(), new PaymentReceived($this->place()));
                throw new RuntimeException('lỗi giữa chừng');
            });
        } catch (RuntimeException) {
        }
        Notification::assertNothingSent();
    }

    public function test_mail_failure_does_not_break_business_flow(): void
    {
        $this->app->instance(Dispatcher::class, new class implements Dispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new RuntimeException('SMTP sập');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new RuntimeException('SMTP sập');
            }
        });
        $order = $this->place();
        $this->orders->confirm($order, $this->admin());
        $this->orders->ship($order, 'GHN', 'S1', $this->admin());           // không ném lỗi ra ngoài
        $this->assertSame(OrderStatus::Shipping, $order->refresh()->status);
    }

    public function test_every_email_renders(): void
    {
        $order = $this->place();
        $order->forceFill(['shipping_carrier' => 'GHN', 'tracking_code' => 'X', 'cancel_reason' => 'Test'])->save();
        $payment = $order->payments()->sole();
        foreach ([new PaymentReceived($order), new OrderShipped($order), new OrderCancelled($order),
            new RefundCompleted($order, $payment), new ReturnInstructions($order), new TierUpgraded(MembershipTier::first()),
            new OrderCompleted($order), new LatePaymentRefund($order, 280000)] as $n) {
            $html = (string) $n->toMail($this->customer())->render();
            $this->assertStringContainsString('GreenGarden', $html, get_class($n));
        }
    }
}

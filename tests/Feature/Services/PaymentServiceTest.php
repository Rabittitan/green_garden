<?php

namespace Tests\Feature\Services;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\PaymentWebhookLog;
use App\Services\OrderService;
use App\Services\Payments\VnpayGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payments = app(PaymentService::class);
    }

    public function test_vnpay_signature_is_hmac_sha512_over_sorted_params(): void
    {
        $gw = new VnpayGateway('TMN', 'SECRET', 'https://pay.test');
        $query = $gw->sign(['vnp_B' => 'x y', 'vnp_A' => '1']);
        $this->assertSame('vnp_A=1&vnp_B=x+y&vnp_SecureHash='.hash_hmac('sha512', 'vnp_A=1&vnp_B=x+y', 'SECRET'), $query);

        parse_str($query, $q);
        $this->assertTrue($gw->parseCallback($q)->signatureValid);
        $q['vnp_B'] = 'z';
        $this->assertFalse($gw->parseCallback($q)->signatureValid);
    }

    public function test_payment_url_contains_amount_x100_and_is_stable_on_retry(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $url1 = $this->payments->checkoutUrl($order, 'https://shop.test/return', '10.0.0.1');
        $url2 = $this->payments->checkoutUrl($order, 'https://shop.test/return', '10.0.0.9');

        $this->assertStringContainsString('vnp_Amount=28000000', $url1);
        $this->assertStringContainsString('vnp_TxnRef='.$order->code.'-1', $url1);
        $this->assertSame($url1, $url2);                                   // cùng giao dịch, không mở giao dịch thứ 2
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_successful_ipn_marks_order_paid_and_is_idempotent(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $charge = $order->payments()->sole();

        $this->assertSame(PaymentService::IPN_OK, $this->ipn($charge));
        $order->refresh();
        $this->assertSame([OrderStatus::Pending, PaymentStatus::Paid, 280000], [$order->status, $order->payment_status, $order->paid_amount]);
        $this->assertNotNull($charge->refresh()->provider_txn_no);
        $this->assertSame(ActorType::Gateway, $order->histories()->get()->last()->actor_type);

        $this->assertSame(PaymentService::IPN_ALREADY_DONE, $this->ipn($charge));   // VNPay gọi lại
        $this->assertSame(280000, $order->refresh()->paid_amount);
        $this->assertSame(2, PaymentWebhookLog::count());
    }

    public function test_ipn_rejects_bad_signature_wrong_amount_and_unknown_txn(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $charge = $order->payments()->sole();

        $this->assertSame(PaymentService::IPN_BAD_SIGNATURE, $this->ipn($charge, tamper: true));
        $this->assertFalse(PaymentWebhookLog::latest('id')->first()->signature_valid);
        $this->assertSame(PaymentService::IPN_BAD_AMOUNT, $this->ipn($charge, amount: 1000));
        $charge->txn_ref = 'KHONGCO-1';
        $this->assertSame(PaymentService::IPN_NOT_FOUND, $this->ipn($charge));
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_failed_payment_allows_retry_with_new_transaction(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $first = $order->payments()->sole();
        $this->assertSame(PaymentService::IPN_OK, $this->ipn($first, success: false));
        $this->assertSame(PaymentState::Failed, $first->refresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);

        $url = $this->payments->checkoutUrl($order, 'https://shop.test/return', '127.0.0.1');
        $this->assertStringContainsString('vnp_TxnRef='.$order->code.'-2', $url);
        $second = $order->payments()->get()->last();
        $this->assertSame(PaymentService::IPN_OK, $this->ipn($second));
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
    }

    public function test_late_ipn_after_expiry_refunds_instead_of_reviving_order(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $charge = $order->payments()->sole();
        $this->travel(20)->minutes();
        app(OrderService::class)->expireOverdue();
        $this->assertSame(30, $this->stock(1));

        $this->assertSame(PaymentService::IPN_OK, $this->ipn($charge));
        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(PaymentStatus::RefundPending, $order->payment_status);
        $this->assertSame(280000, $order->refunds()->sole()->amount);
        $this->assertSame(30, $this->stock(1));                            // không trừ kho lại
    }

    public function test_ipn_success_after_hold_time_but_before_expiry_job_is_accepted(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->travel(16)->minutes();                                      // quá hạn, lệnh hủy chưa chạy
        $this->assertSame(PaymentService::IPN_OK, $this->ipn($order->payments()->sole()));
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status); // khách đã trả tiền -> giữ đơn
        $this->assertSame(0, app(OrderService::class)->expireOverdue());
    }

    public function test_bank_transfer_confirmation_requires_exact_amount(): void
    {
        $order = $this->place(method: PaymentMethod::BankTransfer);
        try {
            $this->payments->confirmBankTransfer($order, 270000, 'FT123', $this->admin());
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('không khớp', $e->getMessage());
        }
        $this->payments->confirmBankTransfer($order, 280000, 'FT123', $this->admin());
        $order->refresh();
        $this->assertSame([OrderStatus::Pending, PaymentStatus::Paid], [$order->status, $order->payment_status]);
        $this->assertSame('FT123', $order->payments()->sole()->provider_txn_no);
    }

    public function test_partial_refund_on_completed_order_and_over_refund_guard(): void
    {
        $orders = app(OrderService::class);
        $order = $this->place();
        $orders->confirm($order, $this->admin());
        $orders->ship($order, 'GHN', 'G1', $this->admin());
        $orders->complete($order, $this->admin());

        $refund = $this->payments->requestRefund($order, 100000, $this->admin(), 'Cây chết sau 3 ngày');
        $this->assertSame(PaymentStatus::RefundPending, $order->refresh()->payment_status);
        try {
            $this->payments->requestRefund($order, 180001, $this->admin(), 'quá tay');   // còn 180k có thể hoàn
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('180.000', $e->getMessage());
        }

        $this->payments->confirmRefund($refund, $this->admin(), 'VNP-RF-1');
        $order->refresh();
        $this->assertSame([OrderStatus::Completed, PaymentStatus::PartiallyRefunded, 100000],
            [$order->status, $order->payment_status, $order->refunded_amount]);

        $this->expectExceptionMessage('không ở trạng thái chờ');
        $this->payments->confirmRefund($refund, $this->admin(), 'lần 2');
    }
}

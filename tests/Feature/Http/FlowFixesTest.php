<?php

namespace Tests\Feature\Http;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Services\OrderService;
use App\Services\Payments\DisabledGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Hồi quy cho các lỗi luồng / logic phát hiện khi rà soát G5-G7. */
class FlowFixesTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_fake_gateway_is_never_enabled_outside_local_or_testing(): void
    {
        $this->app['env'] = 'production';
        $this->assertSame('disabled', PaymentMethod::onlineGatewayMode());
        $this->assertNotContains(PaymentMethod::Vnpay, PaymentMethod::available());
        $this->assertInstanceOf(DisabledGateway::class, app(PaymentGateway::class));
        $this->get(route('payment.fake.show', ['vnp_TxnRef' => 'X']))->assertNotFound();
        $this->assertSame(PaymentService::IPN_BAD_SIGNATURE, app(PaymentService::class)->handleCallback(['vnp_TxnRef' => 'X', 'vnp_SecureHash' => 'a']));

        $this->expectExceptionMessage('không khả dụng');                    // COD vẫn chạy, VNPay bị từ chối
        $this->assertSame(OrderStatus::Pending, $this->place()->status);
        $this->place(method: PaymentMethod::Vnpay);
    }

    public function test_vnpay_without_keys_is_disabled(): void
    {
        config(['shop.payment.gateway' => 'vnpay', 'shop.vnpay.hash_secret' => null]);
        $this->assertFalse(PaymentMethod::Vnpay->isAvailable());
    }

    public function test_bank_transfer_hidden_and_rejected_without_account_number(): void
    {
        config(['shop.bank_transfer.account_no' => '']);
        $this->assertNotContains(PaymentMethod::BankTransfer, PaymentMethod::available());
        $this->actingAs($this->customer())->post(route('cart.add', 1));
        $this->get(route('checkout.form'))->assertOk()->assertDontSee('Chuyển khoản ngân hàng');
        $this->post(route('checkout.store'), array_merge($this->shipping(), ['payment_method' => 'bank_transfer',
            'checkout_token' => session('checkout.token'), 'expected_total' => 280000]))->assertSessionHasErrors('payment_method');
    }

    public function test_return_request_can_be_withdrawn_but_failed_delivery_cannot_be_completed(): void
    {
        $orders = app(OrderService::class);
        $admin = $this->admin();

        $done = $this->place();
        $orders->confirm($done, $admin);
        $orders->ship($done, 'GHN', 'A1', $admin);
        $orders->complete($done, $admin);
        $orders->markReturning($done, ActorType::Customer, $this->customer(), 'Cây héo');
        $this->actingAs($admin)->post(route('admin.orders.cancel-return', $done), ['note' => 'Khách đổi ý'])->assertSessionHas('success');
        $this->assertSame(OrderStatus::Completed, $done->refresh()->status);

        $failed = $this->place();
        $orders->confirm($failed, $admin);
        $orders->ship($failed, 'GHN', 'A2', $admin);
        $orders->markReturning($failed, ActorType::Staff, $admin, 'Khách không nghe máy');
        $this->post(route('admin.orders.complete', $failed))->assertSessionHas('error');          // không được "giao thành công"
        $this->post(route('admin.orders.cancel-return', $failed), ['note' => 'x'])->assertSessionHas('error');
        $this->get(route('admin.orders.show', $failed))->assertOk()->assertDontSee('Giao thành công')->assertDontSee('Hủy yêu cầu trả hàng');
        $this->assertSame(OrderStatus::Returning, $failed->refresh()->status);
    }

    public function test_locked_customer_is_logged_out_on_next_request(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->get(route('orders.index'))->assertOk();
        $user->forceFill(['is_active' => false])->save();
        $this->get(route('orders.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}

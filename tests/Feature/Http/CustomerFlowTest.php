<?php

namespace Tests\Feature\Http;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\Plant;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Luồng của khách qua HTTP thật: route -> middleware -> controller -> service -> view. */
class CustomerFlowTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function checkoutPayload(array $override = []): array
    {
        return array_merge($this->shipping(), [
            'payment_method' => 'cod',
            'checkout_token' => session('checkout.token'),
            'expected_total' => 280000,
        ], $override);
    }

    private function openCheckout(User $user, array $cart = [1 => 1])
    {
        foreach ($cart as $id => $qty) {
            $this->actingAs($user)->post(route('cart.add', $id), ['quantity' => $qty])->assertSessionHas('success');
        }

        return $this->get(route('checkout.form'))->assertOk();
    }

    public function test_guest_can_fill_cart_but_must_login_to_checkout(): void
    {
        $this->post(route('cart.add', 1), ['quantity' => 2])->assertSessionHas('success');
        $this->get(route('cart.index'))->assertOk()->assertSee('Cây Kim Tiền')->assertSee('500.000đ');
        $this->get(route('checkout.form'))->assertRedirect(route('login'));
    }

    public function test_cart_rejects_quantity_over_stock_and_inactive_plants(): void
    {
        $this->post(route('cart.add', 16), ['quantity' => 2])->assertSessionHas('error');
        Plant::find(3)->update(['is_active' => false]);
        $this->post(route('cart.add', 3))->assertSessionHas('error');
        $this->get(route('plants.show', 3))->assertNotFound();
    }

    public function test_unverified_customer_cannot_checkout(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post(route('cart.add', 1));
        $this->get(route('checkout.form'))->assertRedirect(route('verification.notice'));
    }

    public function test_cod_checkout_end_to_end(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->openCheckout($user)->assertSee('280.000đ')->assertSee('Phí vận chuyển');

        $response = $this->post(route('checkout.store'), $this->checkoutPayload());
        $order = Order::sole();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame([OrderStatus::Pending, 280000], [$order->status, $order->total]);
        $this->assertSame(29, $this->stock(1));
        $this->assertSame([], session('cart', []));
        $this->assertNull(session('checkout.token'));
        Mail::assertSent(OrderPlacedMail::class);

        $this->get(route('orders.show', $order))->assertOk()->assertSee($order->code)->assertSee('Chờ xác nhận')->assertSee('Hủy đơn');
        $this->get(route('orders.index'))->assertOk()->assertSee($order->code);
    }

    public function test_new_address_is_saved_to_address_book_on_first_order(): void
    {
        $user = User::factory()->create();
        $this->openCheckout($user);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertRedirect();
        $this->assertTrue(UserAddress::where('user_id', $user->id)->where('is_default', true)->exists());
    }

    public function test_double_submit_returns_the_same_order(): void
    {
        $this->openCheckout($this->customer());
        $payload = $this->checkoutPayload();
        $this->post(route('checkout.store'), $payload)->assertRedirect();
        $this->actingAs($this->customer())->post(route('cart.add', 1));     // tab khác còn giỏ cũ
        $this->post(route('checkout.store'), $payload)->assertRedirect(route('orders.show', Order::sole()));
        $this->assertSame(1, Order::count());
    }

    public function test_price_change_sends_customer_back_to_review(): void
    {
        $this->openCheckout($this->customer());
        Plant::find(1)->update(['price' => 300000]);
        $this->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect(route('checkout.form'))->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        $this->get(route('checkout.form'))->assertSee('330.000đ');            // báo giá mới
    }

    public function test_validation_errors_on_address(): void
    {
        $this->openCheckout($this->customer());
        $this->post(route('checkout.store'), $this->checkoutPayload(['phone' => '123', 'ward' => '']))
            ->assertSessionHasErrors(['phone', 'ward']);
    }

    public function test_coupon_apply_and_remove(): void
    {
        $this->openCheckout($this->customer(), [1 => 2]);
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => 'khongco'])->assertSessionHas('error');
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => 'chaoban'])->assertSessionHas('success');
        $this->get(route('checkout.form'))->assertSee('Mã CHAOBAN')->assertSee('480.000đ');

        $this->post(route('checkout.store'), $this->checkoutPayload(['expected_total' => 480000]))->assertRedirect();
        $this->assertSame('CHAOBAN', Order::sole()->coupon_code);
        $this->assertNull(session('checkout.coupon'));
    }

    public function test_vnpay_flow_through_fake_gateway(): void
    {
        $this->openCheckout($this->customer());
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'vnpay']));
        $order = Order::sole();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);

        $gatewayUrl = $this->get(route('orders.pay', $order))->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/thanh-toan/gia-lap?', $gatewayUrl);
        $this->get($gatewayUrl)->assertOk()->assertSee('280.000đ');

        parse_str(parse_url($gatewayUrl, PHP_URL_QUERY), $q);
        $returnUrl = $this->post(route('payment.fake.submit'), [
            'vnp_TxnRef' => $q['vnp_TxnRef'], 'return_url' => $q['vnp_ReturnUrl'], 'outcome' => 'success',
        ])->assertRedirect()->headers->get('Location');

        $order->refresh();
        $this->assertSame([OrderStatus::Pending, PaymentStatus::Paid], [$order->status, $order->payment_status]);
        $this->get($returnUrl)->assertRedirect(route('orders.show', $order))->assertSessionHas('success');
    }

    public function test_vnpay_failure_then_retry(): void
    {
        $this->openCheckout($this->customer());
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'vnpay']));
        $order = Order::sole();
        parse_str(parse_url($this->get(route('orders.pay', $order))->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->post(route('payment.fake.submit'), ['vnp_TxnRef' => $q['vnp_TxnRef'], 'return_url' => $q['vnp_ReturnUrl'], 'outcome' => 'fail']);
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);

        $retry = $this->get(route('orders.pay', $order))->headers->get('Location');
        $this->assertStringContainsString('vnp_TxnRef='.$order->code.'-2', $retry);
    }

    public function test_ipn_endpoint_answers_vnpay_json(): void
    {
        $this->getJson(route('payment.vnpay.ipn', ['vnp_TxnRef' => 'X', 'vnp_SecureHash' => 'bad']))
            ->assertOk()->assertExactJson(['RspCode' => '97', 'Message' => 'Invalid signature']);
    }

    public function test_customer_cancels_own_order_but_not_others(): void
    {
        $order = $this->place();
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($stranger)->post(route('orders.cancel', $order), ['reason' => 'x'])->assertForbidden();

        $this->actingAs($this->customer())->post(route('orders.cancel', $order), ['reason' => 'Đổi ý'])->assertSessionHas('success');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(30, $this->stock(1));
    }

    public function test_expired_order_is_cancelled_when_customer_opens_it(): void
    {
        $order = $this->place(method: \App\Enums\PaymentMethod::Vnpay);
        $this->travel(20)->minutes();
        $this->actingAs($this->customer())->get(route('orders.show', $order))->assertOk()->assertSee('Đã hủy');
        $this->get(route('orders.pay', $order))->assertRedirect(route('orders.show', $order));
    }

    public function test_membership_page_and_address_book(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->get(route('account.membership'))->assertOk()->assertSee('Thành viên')->assertSee('Bạc');
        $this->get(route('addresses.index'))->assertOk()->assertSee('12 Nguyễn Huệ');
        $this->post(route('addresses.store'), array_merge($this->shipping(), ['address_line' => '99 Lê Lợi', 'is_default' => 1]))->assertSessionHas('success');
        $this->assertSame('99 Lê Lợi', $user->defaultAddress()->first()->address_line);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->get(route('profile.edit'))->assertOk()->assertSee('Hạng Thành viên');
    }

    public function test_admin_login_goes_to_dashboard_and_locked_user_cannot_login(): void
    {
        $this->post(route('login.post'), ['email' => 'admin@greenlife.test', 'password' => '123456'])->assertRedirect(route('admin.dashboard'));
        $this->post(route('logout'));
        $this->customer()->forceFill(['is_active' => false])->save();
        $this->post(route('login.post'), ['email' => 'khach@greenlife.test', 'password' => '123456'])->assertSessionHasErrors('email');
    }

    public function test_register_creates_customer_with_base_tier(): void
    {
        $this->post(route('register.post'), ['name' => 'Mới', 'email' => 'moi@x.test', 'password' => 'abc12345',
            'password_confirmation' => 'abc12345', 'role' => 'admin'])->assertRedirect(route('verification.notice'));
        $u = User::where('email', 'moi@x.test')->sole();
        $this->assertFalse($u->isBackOffice());
        $this->assertSame('member', $u->tier->code);
    }
}

<?php

namespace Tests\Feature\Http;

use App\Enums\ActorType;
use App\Exceptions\CouponNotApplicableException;
use App\Models\Coupon;
use App\Models\CouponGiftRule;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Account\CouponGifted;
use App\Services\CouponGiftService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Đợt 3: ví "Mã của tôi" + tặng mã khi mua (tự động theo luật / quản trị viên tặng tay). */
class CouponWalletTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function delivered(?User $user = null, array $cart = [1 => 1]): Order
    {
        $orders = app(OrderService::class);
        $o = $this->place($user, $cart);
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'W'.$o->id, $this->admin());

        return $orders->complete($o, $this->admin());
    }

    private function rule(int $minTotal = 200000): CouponGiftRule
    {
        return CouponGiftRule::create(['name' => 'Quà cảm ơn', 'min_order_total' => $minTotal, 'type' => 'fixed', 'value' => 30000,
            'min_order_amount' => 100000, 'valid_days' => 30]);
    }

    public function test_rule_gifts_once_after_the_return_window_and_only_to_qualifying_orders(): void
    {
        Notification::fake();
        $before = $this->delivered(User::factory()->create(), [1 => 2]);                  // hoàn thành TRƯỚC khi có luật
        $this->travel(1)->minutes();
        $rule = $this->rule();
        $this->travel(1)->minutes();
        $big = $this->delivered(cart: [1 => 1]);                                           // 280.000đ >= 200.000đ
        $small = $this->delivered(User::factory()->create(), [5 => 1]);                    // 75.000đ < 200.000đ
        $returned = $this->delivered(User::factory()->create(), [1 => 1]);
        app(OrderService::class)->markReturning($returned, ActorType::Customer, $returned->user, 'Héo');
        $gifts = app(CouponGiftService::class);

        $this->assertSame(0, $gifts->issueDueGifts());                                     // chưa qua 7 ngày đổi trả
        $this->travel((int) config('shop.return_window_days') + 1)->days();
        $this->assertSame(1, $gifts->issueDueGifts());
        $this->assertSame(0, $gifts->issueDueGifts());                                     // chạy lại: không tặng trùng

        $c = Coupon::where('gift_rule_id', $rule->id)->sole();
        $this->assertSame([$this->customer()->id, $big->id, 1, 30000], [$c->owner_user_id, $c->source_order_id, $c->usage_limit, $c->value]);
        $this->assertMatchesRegularExpression('/^QUA[A-Z2-9]{8}$/', $c->code);
        $this->assertTrue($c->ends_at->between(now()->addDays(29), now()->addDays(31)));
        Notification::assertSentTo($this->customer(), CouponGifted::class);
        unset($before, $small);

        $this->actingAs($this->customer())->get(route('account.coupons'))->assertOk()
            ->assertSee($c->code)->assertSee('Giảm 30.000đ')->assertSee('Đơn từ 100.000đ');
    }

    public function test_gifted_coupon_belongs_to_its_owner_and_is_returned_when_the_order_is_cancelled(): void
    {
        $c = app(CouponGiftService::class)->giftManually($this->customer(), ['name' => 'Xin lỗi giao chậm', 'type' => 'percent', 'value' => 10,
            'max_discount_amount' => 50000, 'valid_days' => 7], $this->admin());

        $other = User::factory()->create();
        try {
            $this->place($other, [1 => 1], coupon: $c->code);
            $this->fail('Người khác không được dùng mã tặng riêng');
        } catch (CouponNotApplicableException $e) {
            $this->assertStringContainsString('quà tặng riêng', $e->getMessage());
        }

        $order = $this->place(cart: [1 => 1], coupon: $c->code);
        $this->assertSame(1, $c->refresh()->used_count);
        $this->actingAs($this->customer())->get(route('account.coupons'))->assertSee('Đã dùng');
        app(OrderService::class)->cancel($order, ActorType::Customer, $this->customer(), 'Đổi ý');
        $this->assertSame(0, $c->refresh()->used_count);                                   // hủy đơn: trả lại lượt
        $this->assertSame(1, Coupon::walletUsable($this->customer()->id)->count());
        $this->artisan('shop:reconcile')->assertSuccessful();
    }

    public function test_admin_gifts_manually_from_customer_page(): void
    {
        Notification::fake();
        $this->actingAs($this->admin())->get(route('admin.customers.show', $this->customer()))->assertSee('Tặng mã cho khách');
        $this->post(route('admin.customers.gift', $this->customer()), ['name' => 'Tri ân', 'type' => 'free_shipping', 'valid_days' => 15])
            ->assertSessionHas('success');
        $c = Coupon::whereNotNull('owner_user_id')->sole();
        $this->assertSame(['free_shipping', 0, $this->admin()->id], [$c->type->value, $c->value, $c->created_by]);
        Notification::assertSentTo($this->customer(), CouponGifted::class);

        $this->post(route('admin.customers.gift', $this->customer()), ['name' => 'x', 'type' => 'percent', 'value' => 150, 'valid_days' => 7])
            ->assertSessionHasErrors('value');
        $staff = User::factory()->staff()->create();
        $this->post(route('admin.customers.gift', $staff), ['name' => 'x', 'type' => 'fixed', 'value' => 1000, 'valid_days' => 7])->assertSessionHas('error');

        // Danh sách mã chung không lẫn mã tặng; tab riêng có
        $this->get(route('admin.coupons.index'))->assertDontSee($c->code);
        $this->get(route('admin.coupons.index', ['loai' => 'tang']))->assertSee($c->code)->assertSee($this->customer()->name);
    }

    public function test_admin_manages_gift_rules_and_checkout_offers_wallet_codes(): void
    {
        $this->actingAs($this->admin())->post(route('admin.coupon-gifts.store'), ['name' => 'Quà 1 triệu', 'min_order_total' => 1000000,
            'type' => 'percent', 'value' => 5, 'max_discount_amount' => 100000, 'valid_days' => 60, 'is_active' => 1])->assertRedirect(route('admin.coupon-gifts.index'));
        $rule = CouponGiftRule::sole();
        $this->get(route('admin.coupon-gifts.index'))->assertSee('Quà 1 triệu')->assertSee('Giảm 5% (tối đa 100.000đ)');
        $this->put(route('admin.coupon-gifts.update', $rule), ['name' => 'Quà 1 triệu', 'min_order_total' => 1000000, 'type' => 'fixed',
            'value' => 50000, 'valid_days' => 60, 'is_active' => 0])->assertRedirect();
        $this->assertSame([false, 50000], [$rule->refresh()->is_active, $rule->value]);

        $c = app(CouponGiftService::class)->giftManually($this->customer(), ['name' => 'Quà', 'type' => 'fixed', 'value' => 20000, 'valid_days' => 7], $this->admin());
        $this->actingAs($this->customer());
        session(['cart' => [1 => 1]]);
        $this->get(route('checkout.form'))->assertOk()->assertSee('data-testid="my-coupons"', false)->assertSee($c->code);
        $this->post(route('checkout.coupon.apply'), ['coupon_code' => $c->code])->assertSessionHas('success');
    }
}

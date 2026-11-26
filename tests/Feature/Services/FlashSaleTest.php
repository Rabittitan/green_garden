<?php

namespace Tests\Feature\Services;

use App\Enums\ActorType;
use App\Enums\FlashSaleStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\PriceChangedException;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\User;
use App\Services\FlashSaleService;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Đợt 4: flash sale — nhân viên tạo, quản trị viên duyệt; giá sale + vẫn cộng hạng và mã; suất có giới hạn. */
class FlashSaleTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private FlashSaleService $sales;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sales = app(FlashSaleService::class);
        $this->staff = User::factory()->staff()->create();
    }

    /** Đợt sale đã duyệt, đang chạy: cây 1 (giá 250.000đ) sale 200.000đ. */
    private function running(?int $limit = null, int $price = 200000, int $plantId = 1): FlashSaleItem
    {
        $sale = new FlashSale(['name' => 'Sale', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $sale->forceFill(['created_by' => $this->staff->id, 'status' => FlashSaleStatus::Approved, 'approved_by' => $this->admin()->id, 'approved_at' => now()])->save();

        return $sale->items()->create(['plant_id' => $plantId, 'sale_price' => $price, 'quantity_limit' => $limit]);
    }

    private function window(): array
    {
        $tz = config('shop.display_timezone');

        return ['name' => 'Cuối tuần', 'starts_at' => now($tz)->addHour()->format('Y-m-d\TH:i'), 'ends_at' => now($tz)->addHours(5)->format('Y-m-d\TH:i')];
    }

    public function test_staff_creates_admin_approves_and_admin_cannot_create(): void
    {
        try {
            $this->sales->create($this->admin(), $this->window(), [1 => ['sale_price' => 1000, 'quantity_limit' => null]]);
            $this->fail('Quản trị viên không được tạo flash sale');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('do nhân viên tạo', $e->getMessage());
        }
        try {
            $this->sales->create($this->staff, $this->window(), [1 => ['sale_price' => 250000, 'quantity_limit' => null]]);
            $this->fail('Giá sale phải thấp hơn giá bán');
        } catch (BusinessException) {
        }

        $sale = $this->sales->create($this->staff, $this->window(), [1 => ['sale_price' => 199000, 'quantity_limit' => 5]]);
        $this->assertSame(FlashSaleStatus::Draft, $sale->status);
        try {
            $this->sales->approve($sale, $this->admin());                                   // chưa gửi duyệt
            $this->fail();
        } catch (BusinessException) {
        }
        $this->sales->submit($sale, $this->staff);
        try {
            $this->sales->reject($sale->refresh(), $this->admin(), ' ');
            $this->fail('Từ chối phải có lý do');
        } catch (BusinessException) {
        }
        $this->sales->reject($sale, $this->admin(), 'Giá thấp quá');
        $this->assertSame([FlashSaleStatus::Rejected, 'Giá thấp quá'], [$sale->refresh()->status, $sale->reject_reason]);

        $this->sales->update($sale, $this->staff, $this->window(), [1 => ['sale_price' => 220000, 'quantity_limit' => 5]]);
        $this->sales->submit($sale->refresh(), $this->staff);
        $this->sales->approve($sale->refresh(), $this->admin());
        $this->assertSame([FlashSaleStatus::Approved, $this->admin()->id], [$sale->refresh()->status, $sale->approved_by]);

        // Cây đã có trong đợt duyệt trùng giờ -> đợt thứ 2 không duyệt được
        $other = $this->sales->create(User::factory()->staff()->create(), $this->window(), [1 => ['sale_price' => 210000, 'quantity_limit' => null]]);
        $this->sales->submit($other, $other->creator);
        try {
            $this->sales->approve($other->refresh(), $this->admin());
            $this->fail('Trùng giờ phải bị chặn');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('trùng khung giờ', $e->getMessage());
        }

        // Đã duyệt: nhân viên không hủy được, quản trị viên hủy được
        try {
            $this->sales->cancel($sale, $this->staff);
            $this->fail();
        } catch (BusinessException) {
        }
        $this->sales->cancel($sale, $this->admin());
        $this->assertSame(FlashSaleStatus::Cancelled, $sale->refresh()->status);
    }

    public function test_sale_price_applies_only_while_running_and_tier_plus_coupon_still_stack(): void
    {
        $user = $this->customer();
        $pricing = app(PricingService::class);
        $this->assertSame(250000, $pricing->quoteCart($user, [1 => 1])->lines[0]->unitPrice);   // chưa có sale

        $item = $this->running();
        $q = $pricing->quoteCart($user, [1 => 2]);
        $this->assertSame([200000, 400000, $item->id], [$q->lines[0]->unitPrice, $q->subtotal(), $q->lines[0]->flashItem?->id]);

        // Hạng Vàng (5%) + mã 10% cộng tiếp trên giá sale
        $gold = $this->withTier(User::factory()->create(), 'gold');
        $coupon = Coupon::create(['code' => 'SALE10', 'name' => 'x', 'type' => 'percent', 'value' => 10]);
        $q = $pricing->quoteCart($gold, [1 => 2], 'SALE10');
        $this->assertSame([400000, 20000, 38000], [$q->subtotal(), $q->memberDiscount(), $q->couponDiscount()]);
        unset($coupon);

        $item->sale->forceFill(['starts_at' => now()->addMinute()])->save();                    // chưa tới giờ
        $this->assertSame(250000, $pricing->quoteCart($user, [1 => 1])->lines[0]->unitPrice);
        $item->sale->forceFill(['starts_at' => now()->subHours(2), 'ends_at' => now()->subMinute()])->save();   // đã qua giờ
        $this->assertSame(250000, $pricing->quoteCart($user, [1 => 1])->lines[0]->unitPrice);
        $item->sale->forceFill(['ends_at' => now()->addHour(), 'status' => FlashSaleStatus::Submitted])->save();   // chưa duyệt
        $this->assertSame(250000, $pricing->quoteCart($user, [1 => 1])->lines[0]->unitPrice);
    }

    public function test_limited_quota_is_consumed_released_and_reconciled(): void
    {
        $item = $this->running(limit: 2);
        $a = $this->place(User::factory()->create(), [1 => 2]);
        $this->assertSame([200000, $item->id], [$a->items()->sole()->unit_price, $a->items()->sole()->flash_sale_item_id]);
        $this->assertSame(2, $item->refresh()->sold_count);

        // Hết suất: giá thường + báo khách
        $q = app(PricingService::class)->quoteCart($this->customer(), [1 => 1]);
        $this->assertSame(250000, $q->lines[0]->unitPrice);
        $this->assertStringContainsString('chỉ còn 0 suất', implode(' ', $q->notices));
        $b = $this->place(cart: [1 => 1]);
        $this->assertSame([250000, null], [$b->items()->sole()->unit_price, $b->items()->sole()->flash_sale_item_id]);

        // Còn 1 suất mà đặt 2 cây: cả dòng tính giá thường (không tách dòng)
        app(OrderService::class)->cancel($a, ActorType::Customer, $a->user, 'Đổi ý');
        $this->assertSame(0, $item->refresh()->sold_count);                                      // hủy -> trả suất
        $this->place(User::factory()->create(), [1 => 1]);
        $c = $this->place(User::factory()->create(), [1 => 2]);
        $this->assertSame(250000, $c->items()->sole()->unit_price);
        $this->assertSame(1, $item->refresh()->sold_count);
        $this->assertLedgerConsistent();
        $this->artisan('shop:reconcile')->assertSuccessful();
    }

    public function test_sale_ending_between_preview_and_checkout_raises_price_changed(): void
    {
        $item = $this->running();
        $expected = app(PricingService::class)->quoteCart($this->customer(), [1 => 1])->total();
        $this->sales->cancel($item->sale, $this->admin());
        $this->expectException(PriceChangedException::class);
        $this->place(cart: [1 => 1], expectedTotal: $expected);
    }

    public function test_storefront_shows_sale_price_and_countdown(): void
    {
        $this->running(limit: 10);
        $this->get(route('home'))->assertOk()->assertSee('data-testid="flash-sale"', false)->assertSee('200.000đ')->assertSee('Còn 10 suất');
        $this->get(route('plants.show', 1))->assertOk()->assertSee('Flash sale')->assertSee('còn <strong>10</strong>/10 suất', false);
        $this->get(route('plants.index'))->assertOk()->assertSee('−20%');
        $this->actingAs($this->customer());
        session(['cart' => [1 => 1]]);
        $this->get(route('cart.index'))->assertOk()->assertSee('Flash sale')->assertSee('<s>250.000đ</s>', false);
        $this->get(route('checkout.form'))->assertOk()->assertSee('Sale');
    }

    public function test_admin_pages_and_permissions(): void
    {
        $sale = $this->sales->create($this->staff, $this->window(), [1 => ['sale_price' => 199000, 'quantity_limit' => 3]]);
        $this->actingAs($this->staff)->post(route('admin.flash-sales.submit', $sale))->assertSessionHas('success');
        $this->post(route('admin.flash-sales.approve', $sale))->assertNoAccess();               // nhân viên không duyệt
        $this->actingAs($this->admin())->get(route('admin.flash-sales.create'))->assertRedirect(route('admin.flash-sales.index'));
        $this->get(route('admin.flash-sales.show', $sale))->assertOk()->assertSee('Duyệt')->assertSee('Từ chối');
        $this->post(route('admin.flash-sales.approve', $sale))->assertSessionHas('success');
        $this->assertSame(FlashSaleStatus::Approved, $sale->refresh()->status);

        $limited = User::factory()->staff()->create(['permissions' => ['orders']]);
        $this->actingAs($limited)->get(route('admin.flash-sales.index'))->assertNoAccess();
        $this->actingAs($this->staff)->post(route('admin.flash-sales.store'), ['name' => 'X'] + $this->window() + ['items' => [3 => ['sale_price' => 100000]]])
            ->assertRedirect();
        $this->assertSame(2, FlashSale::count());
        $this->assertSame([3, 100000], [FlashSale::latest('id')->first()->items()->sole()->plant_id, FlashSale::latest('id')->first()->items()->sole()->sale_price]);
    }
}

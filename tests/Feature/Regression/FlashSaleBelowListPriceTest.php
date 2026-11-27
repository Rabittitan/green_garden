<?php

namespace Tests\Feature\Regression;

use App\Enums\FlashSaleStatus;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Plant;
use App\Models\User;
use App\Services\PricingService;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * Lỗi phân tích 26/11: hạ giá thường của cây XUỐNG DƯỚI giá flash sale trong lúc sale đang chạy thì khách vẫn bị tính
 * giá "sale" (cao hơn giá thường), ăn mất 1 suất, trang cây hiện nhãn "−-60%". Code cũ: báo giá = 200.000đ.
 * Đúng: khách luôn trả giá thấp hơn trong hai giá; sale không còn rẻ hơn thì như không có sale.
 */
class FlashSaleBelowListPriceTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function runningSale(): FlashSaleItem
    {
        $sale = new FlashSale(['name' => 'Sale cuối tuần', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $sale->forceFill(['created_by' => User::factory()->staff()->create()->id, 'status' => FlashSaleStatus::Approved,
            'approved_by' => $this->admin()->id, 'approved_at' => now()])->save();

        return $sale->items()->create(['plant_id' => 1, 'sale_price' => 200000, 'quantity_limit' => 10]);   // giá thường 250.000đ
    }

    /** Admin hạ giá thường qua form sửa cây (kèm cảnh báo đợt sale thôi tác dụng). */
    private function lowerListPrice(int $price): void
    {
        $p = Plant::find(1);
        $this->actingAs($this->admin())->put(route('admin.plants.update', $p), ['category_id' => $p->category_id, 'name' => $p->name, 'price' => $price,
            'reorder_point' => $p->reorder_point, 'light' => $p->light, 'element' => $p->element, 'is_active' => 1])
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'không cao hơn giá flash sale') && str_contains($w, '"Sale cuối tuần" '.Format::money(200000)));
        auth()->logout();
    }

    public function test_customer_pays_the_lower_list_price_and_no_slot_is_used(): void
    {
        $slot = $this->runningSale();
        $this->assertSame(200000, app(PricingService::class)->quoteCart($this->customer(), [1 => 1])->lines[0]->unitPrice);   // trước khi hạ giá

        $this->lowerListPrice(125000);
        $line = app(PricingService::class)->quoteCart($this->customer(), [1 => 1])->lines[0];
        $this->assertSame([125000, null], [$line->unitPrice, $line->flashItem]);

        $item = $this->place(cart: [1 => 1])->items()->sole();
        $this->assertSame([125000, null], [$item->unit_price, $item->flash_sale_item_id]);
        $this->assertSame(0, $slot->refresh()->sold_count);
        $this->assertLedgerConsistent();
    }

    public function test_pages_stop_advertising_a_sale_that_is_not_cheaper(): void
    {
        $this->runningSale();
        $this->lowerListPrice(125000);

        $plantPage = $this->get(route('plants.show', 1))->assertOk()->getContent();
        $this->assertStringNotContainsString('gg-flash-box', $plantPage);
        $this->assertStringNotContainsString('−-', $plantPage);                              // nhãn giảm giá âm
        $home = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('−-', $home);
        $this->assertStringNotContainsString(Format::money(200000), $home);
        $this->assertStringNotContainsString('−-', $this->get(route('plants.index'))->getContent());

        $this->actingAs($this->customer())->post(route('cart.add', 1));
        $cart = $this->get(route('cart.index'))->assertOk()->getContent();
        $this->assertStringContainsString(Format::money(125000), $cart);
        $this->assertStringNotContainsString(Format::money(200000), $cart);
    }

    /** Giá thường tăng lại trên giá sale (sale còn chạy): sale có hiệu lực trở lại. */
    public function test_sale_applies_again_when_list_price_goes_back_up(): void
    {
        $this->runningSale();
        $this->lowerListPrice(200000);                                                        // bằng giá sale: cũng không phải sale
        $this->assertSame(200000, app(PricingService::class)->quoteCart($this->customer(), [1 => 1])->lines[0]->unitPrice);
        $this->assertNull(app(PricingService::class)->quoteCart($this->customer(), [1 => 1])->lines[0]->flashItem);

        $p = Plant::find(1);
        $this->actingAs($this->admin())->put(route('admin.plants.update', $p), ['category_id' => $p->category_id, 'name' => $p->name, 'price' => 300000,
            'reorder_point' => $p->reorder_point, 'light' => $p->light, 'element' => $p->element, 'is_active' => 1])->assertSessionMissing('warning');
        $line = app(PricingService::class)->quoteCart($this->customer(), [1 => 1])->lines[0];
        $this->assertSame(200000, $line->unitPrice);
        $this->assertNotNull($line->flashItem);
    }
}

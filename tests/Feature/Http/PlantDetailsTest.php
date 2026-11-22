<?php

namespace Tests\Feature\Http;

use App\Models\Plant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** K8: thông tin chi tiết của cây (kích thước, chậu, tốc độ lớn, tưới nước, sinh trưởng, chăm sóc). */
class PlantDetailsTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    /** @return array<string, mixed> */
    private function base(array $extra = []): array
    {
        return ['category_id' => 1, 'name' => 'Cây Thử', 'price' => 99000, 'reorder_point' => 3,
            'light' => 'ua_bong', 'element' => 'kim', 'is_active' => 1] + $extra;
    }

    public function test_product_page_shows_seeded_details(): void
    {
        $plant = Plant::where('name', 'Cây Kim Tiền')->sole();
        $this->get(route('plants.show', $plant->id))->assertOk()
            ->assertSee('Thông tin chi tiết')->assertSee('href="#chi-tiet"', false)
            ->assertSee('Cao 50–60 cm')->assertSee('Chậu sứ trắng Ø20 cm')->assertSee('Chậm')->assertSee('1 lần/tuần')
            ->assertSee('Quá trình sinh trưởng')->assertSee('Thân củ dưới đất trữ nước')
            ->assertSee('<li>Đặt nơi có ánh sáng tán xạ, tránh nắng gắt chiếu thẳng làm cháy lá</li>', false)
            ->assertSee($plant->sku);
    }

    public function test_plant_without_details_hides_the_whole_block(): void
    {
        $plant = Plant::whereNull('care_guide')->whereNull('height')->firstOrFail();      // cây mẫu không có dữ liệu chi tiết
        $this->get(route('plants.show', $plant->id))->assertOk()
            ->assertDontSee('Thông tin chi tiết')->assertDontSee('#chi-tiet', false)->assertDontSee('Tốc độ lớn');
    }

    public function test_only_filled_sections_are_shown_and_text_is_escaped(): void
    {
        $plant = Plant::find(1);
        $plant->update(['height' => null, 'pot_size' => null, 'growth_rate' => null, 'watering' => null, 'growth_info' => null,
            'care_guide' => "- Tưới ít\n\n• <script>alert(1)</script>\n   * Bón phân   "]);
        $page = $this->get(route('plants.show', 1))->assertOk()
            ->assertSee('Cách chăm sóc')->assertDontSee('Quá trình sinh trưởng')->assertDontSee('Kích thước cây')
            ->assertSee('<li>Tưới ít</li>', false)->assertSee('<li>Bón phân</li>', false)            // bỏ gạch đầu dòng tự gõ, bỏ dòng trống
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->assertSame(1, preg_match('#<ul class="gg-care[^"]*">(.*?)</ul>#s', $page->getContent(), $m));
        $this->assertSame(3, substr_count($m[1], '<li>'));
        $this->assertStringNotContainsString('<script>alert(1)', $page->getContent());
    }

    public function test_admin_creates_and_edits_details(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.plants.store'), $this->base(['opening_stock' => 5, 'cost_price' => 50000,
            'height' => 'Cao 30 cm', 'growth_rate' => 'fast', 'care_guide' => 'Tưới 2 ngày/lần']))->assertSessionHasNoErrors();
        $plant = Plant::where('name', 'Cây Thử')->sole();
        $this->assertSame(['Cao 30 cm', 'fast', 'Tưới 2 ngày/lần', null], [$plant->height, $plant->growth_rate, $plant->care_guide, $plant->pot_size]);

        $this->get(route('admin.plants.edit', $plant))->assertOk()->assertSee('Thông tin chi tiết')->assertSee('value="Cao 30 cm"', false);
        $this->put(route('admin.plants.update', $plant), $this->base(['height' => 'Cao 35 cm', 'pot_size' => 'Chậu Ø12 cm',
            'growth_rate' => '', 'watering' => '1 lần/tuần', 'growth_info' => 'Lớn nhanh mùa mưa', 'care_guide' => '']))   // form gửi đủ ô; ô trống -> NULL
            ->assertSessionHasNoErrors();
        $plant->refresh();
        $this->assertSame(['Cao 35 cm', 'Chậu Ø12 cm', null, '1 lần/tuần', 'Lớn nhanh mùa mưa', null],
            [$plant->height, $plant->pot_size, $plant->growth_rate, $plant->watering, $plant->growth_info, $plant->care_guide]);
        $this->assertSame(5, $plant->stock);                                                  // sửa thông tin không đụng tồn kho
        $this->assertLedgerConsistent();
    }

    public function test_invalid_details_are_rejected_by_form_and_database(): void
    {
        $plant = Plant::find(1);
        $this->actingAs($this->admin())->put(route('admin.plants.update', $plant), $this->base([
            'growth_rate' => 'super', 'height' => str_repeat('x', 61), 'care_guide' => str_repeat('x', 5001),
        ]))->assertSessionHasErrors(['growth_rate', 'height', 'care_guide']);
        $this->assertSame('slow', $plant->refresh()->growth_rate);

        $this->expectException(QueryException::class);                                      // CHECK chk_plants_growth_rate
        DB::table('plants')->where('id', 1)->update(['growth_rate' => 'super']);
    }
}

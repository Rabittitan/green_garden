<?php

namespace Tests\Feature\Regression;

use App\Models\Category;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * U1 (audit, đã chốt nghiệp vụ): bộ lọc giá ở trang danh sách cây — các khoảng KHÔNG chồng nhau, không bỏ sót.
 *   duoi-100 : price < 100.000
 *   100-300  : 100.000 <= price < 300.000
 *   300-500  : 300.000 <= price < 500.000
 *   tren-500 : price >= 500.000
 */
class PriceRangeFilterTest extends TestCase
{
    use RefreshDatabase;

    private const PREFIX = 'Mốc giá';

    /** giá => khoảng duy nhất được phép chứa giá đó */
    private const EXPECTED = [
        1 => 'duoi-100', 99_999 => 'duoi-100',
        100_000 => '100-300', 100_001 => '100-300', 299_999 => '100-300',
        300_000 => '300-500', 300_001 => '300-500', 499_999 => '300-500',
        500_000 => 'tren-500', 500_001 => 'tren-500', 5_000_000 => 'tren-500',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::first();
        foreach (array_keys(self::EXPECTED) as $price) {
            Plant::create(['category_id' => $category->id, 'sku' => "MG{$price}", 'name' => self::PREFIX." {$price}",
                'slug' => "moc-gia-{$price}", 'price' => $price, 'reorder_point' => 0, 'light' => 'ua_sang', 'element' => 'kim',
                'is_active' => true]);
        }
    }

    /** @return list<int> giá của các cây "Mốc giá" hiện ra với bộ lọc $range */
    private function pricesShownFor(string $range): array
    {
        // Đọc qua MỌI trang (danh sách phân trang 9 cây/trang): không được kết luận chỉ từ trang đầu
        $prices = [];
        for ($page = 1; ; $page++) {
            $plants = $this->get(route('plants.index', ['price' => $range, 'keyword' => self::PREFIX, 'page' => $page]))
                ->assertOk()->viewData('plants');
            foreach ($plants->items() as $plant) {
                $prices[] = $plant->price;
            }
            if (! $plants->hasMorePages()) {
                break;
            }
        }
        $this->assertSame($plants->total(), count($prices), 'Số cây đọc được phải bằng tổng của bộ lọc');
        sort($prices);

        return $prices;
    }

    public function test_each_boundary_price_belongs_to_exactly_one_range(): void
    {
        $shown = [];
        foreach (['duoi-100', '100-300', '300-500', 'tren-500'] as $range) {
            foreach ($this->pricesShownFor($range) as $price) {
                $shown[$price][] = $range;
            }
        }

        foreach (self::EXPECTED as $price => $range) {
            $this->assertSame([$range], $shown[$price] ?? [], "Giá {$price}đ phải thuộc đúng 1 khoảng: {$range}");
        }
    }

    public function test_no_or_unknown_price_filter_shows_everything(): void
    {
        $all = array_keys(self::EXPECTED);
        sort($all);
        $this->assertSame($all, $this->pricesShownFor(''));
        $this->assertSame($all, $this->pricesShownFor('khong-hop-le'));
    }
}

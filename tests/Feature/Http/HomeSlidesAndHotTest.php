<?php

namespace Tests\Feature\Http;

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Models\HomeSlide;
use App\Models\Plant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Trang chủ: slide ảnh do admin quản lý + mục "Sản phẩm hot" (bán chạy 30 ngày). */
class HomeSlidesAndHotTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    /** @return array<string, mixed> */
    private function slide(array $extra = []): array
    {
        return array_merge(['title' => 'Slide thử', 'subtitle' => 'Dòng phụ', 'link_url' => '/cay-canh?element=kim',
            'button_label' => 'Xem', 'sort_order' => 1, 'is_active' => 1], $extra);
    }

    public function test_home_shows_active_slides_in_order_and_falls_back_to_banner(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(5, substr_count($html, 'class="gg-slide"'));                       // 5 slide mẫu
        $this->assertStringContainsString('data-gg-slider', $html);
        $this->assertStringContainsString('js/slider.js', $html);
        $this->assertLessThan(strpos($html, 'Cây hợp mệnh Kim'), strpos($html, 'Cây Cảnh Phong Thủy &amp; Nội Thất'));

        HomeSlide::where('title', 'Cây hợp mệnh Kim')->update(['is_active' => false]);
        HomeSlide::where('title', 'Bí quyết chăm cây')->update(['sort_order' => 0]);        // đưa lên đầu
        $html = $this->get(route('home'))->getContent();
        $this->assertSame(4, substr_count($html, 'class="gg-slide"'));
        $this->assertStringNotContainsString('Cây hợp mệnh Kim', $html);
        $this->assertLessThan(strpos($html, 'Cây Cảnh Phong Thủy &amp; Nội Thất'), strpos($html, 'Bí quyết chăm cây'));

        HomeSlide::query()->update(['is_active' => false]);
        $this->get(route('home'))->assertDontSee('data-gg-slider', false)->assertSee('Khám phá ngay');   // banner mặc định
    }

    public function test_staff_manages_slides_with_upload_and_old_files_are_removed(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.slides.index'))->assertOk()->assertSee('Slide trang chủ');

        $this->post(route('admin.slides.store'), $this->slide(['image' => UploadedFile::fake()->image('s.jpg', 1600, 600)]))
            ->assertRedirect(route('admin.slides.index'));
        $s = HomeSlide::where('title', 'Slide thử')->sole();
        $first = substr($s->image, strlen('/storage/'));
        $this->assertMatchesRegularExpression('#^slides/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#', $first);
        Storage::disk('public')->assertExists($first);

        $this->put(route('admin.slides.update', $s), $this->slide(['title' => 'Đã sửa', 'is_active' => 0]))->assertSessionHas('success');
        $this->assertSame(['Đã sửa', false, '/storage/'.$first], [$s->fresh()->title, $s->fresh()->is_active, $s->fresh()->image]);   // không tải ảnh = giữ ảnh

        $this->put(route('admin.slides.update', $s), $this->slide(['image' => UploadedFile::fake()->image('m.png', 1600, 600)]));
        Storage::disk('public')->assertMissing($first);
        $second = substr($s->fresh()->image, strlen('/storage/'));
        $this->delete(route('admin.slides.destroy', $s))->assertRedirect(route('admin.slides.index'));
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($s->fresh());
    }

    public function test_slide_input_is_validated(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.slides.store'), $this->slide())->assertSessionHasErrors('image');                // thiếu ảnh
        foreach (['javascript:alert(1)', '//evil.example/x', 'http://khong-an-toan.vn'] as $bad) {
            $this->post(route('admin.slides.store'), $this->slide(['image_url' => 'https://a.vn/x.jpg', 'link_url' => $bad]))
                ->assertSessionHasErrors('link_url');
        }
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->post(route('admin.slides.store'), $this->slide(['image' => $svg]))->assertSessionHasErrors('image');

        $this->post(route('admin.slides.store'), $this->slide(['image_url' => 'https://a.vn/x.jpg', 'link_url' => 'https://a.vn']))->assertSessionHas('success');
        for ($i = HomeSlide::count(); $i < HomeSlide::MAX; $i++) {
            HomeSlide::create(['image' => 'https://a.vn/'.$i.'.jpg', 'sort_order' => $i]);
        }
        $this->post(route('admin.slides.store'), $this->slide(['image_url' => 'https://a.vn/y.jpg']))
            ->assertSessionHas('error', 'Tối đa '.HomeSlide::MAX.' slide. Hãy xóa bớt slide cũ trước khi thêm.');
        $this->assertSame(HomeSlide::MAX, HomeSlide::count());
    }

    public function test_slide_text_is_escaped(): void
    {
        HomeSlide::query()->delete();
        HomeSlide::create(['title' => '<script>alert(1)</script>', 'image' => 'https://a.vn/x.jpg', 'is_active' => true]);
        $this->get(route('home'))->assertDontSee('<script>alert(1)', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_hot_products_rank_recent_real_sales_only(): void
    {
        $orders = app(OrderService::class);
        $this->place(cart: [6 => 3]);                                                        // chờ xác nhận: tính
        $done = $this->place(User::factory()->create(), [12 => 5]);                          // hoàn thành: tính
        $orders->confirm($done, $this->admin());
        $orders->ship($done, 'GHN', 'H1', $this->admin());
        $orders->complete($done, $this->admin());
        $cancelled = $this->place(User::factory()->create(), [2 => 9]);
        $orders->cancel($cancelled, ActorType::Customer, $cancelled->user, 'Đổi ý');         // đã hủy: không tính
        $old = $this->place(User::factory()->create(), [4 => 9]);
        DB::table('orders')->where('id', $old->id)->update(['placed_at' => now()->subDays(40)]);   // quá 30 ngày: không tính
        $this->place(User::factory()->create(), [7 => 9], PaymentMethod::Vnpay);           // chờ thanh toán: không tính
        $inactive = $this->place(User::factory()->create(), [9 => 9]);
        Plant::whereKey(9)->update(['is_active' => false]);                                  // cây ngừng bán: không hiện
        unset($inactive);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#data-testid="hot-plants">(.*?)<div class="gg-section-head"#s', $html, $m));
        $this->assertSame((int) config('shop.home.hot_limit'), substr_count($m[1], 'class="card plant-card'));   // bù đủ 8 cây
        $this->assertStringContainsString('Đã bán 5', $m[1]);
        $this->assertStringContainsString('Đã bán 3', $m[1]);
        $this->assertLessThan(strpos($m[1], 'Đã bán 3'), strpos($m[1], 'Đã bán 5'));         // nhiều hơn đứng trước
        $this->assertSame(2, substr_count($m[1], 'Đã bán'));                                  // chỉ 2 cây có lượt bán hợp lệ
        $this->assertStringContainsString(Plant::find(12)->name, $m[1]);
        $this->assertStringNotContainsString('>'.Plant::find(9)->name.'<', $m[1]);
    }
}

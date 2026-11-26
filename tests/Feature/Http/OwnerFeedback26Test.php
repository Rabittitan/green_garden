<?php

namespace Tests\Feature\Http;

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\Article;
use App\Models\Order;
use App\Models\Plant;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Góp ý chủ shop 26/11: biểu đồ tròn ở Thống kê, "Xem trước" khi soạn bài, dải "Sản phẩm hot" trượt ngang, slide tự chạy. */
class OwnerFeedback26Test extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function deliver(Order $o): Order
    {
        $orders = app(OrderService::class);
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'P'.$o->id, $this->admin());

        return $orders->complete($o, $this->admin());
    }

    private function net(Order $o): int
    {
        $o->refresh();

        return $o->subtotal - $o->member_discount - $o->coupon_discount - $o->refunded_amount;
    }

    public function test_dashboard_pies_split_orders_by_status_and_revenue_by_payment_method(): void
    {
        config(['shop.bank_transfer.account_no' => '0123456789']);
        $cod = $this->deliver($this->place(cart: [1 => 2]));
        $bank = $this->place(User::factory()->create(), [3 => 1], PaymentMethod::BankTransfer);
        app(PaymentService::class)->confirmBankTransfer($bank, $bank->total, 'CK-1', $this->admin());
        $bank = $this->deliver($bank->refresh());
        $this->place(User::factory()->create(), [5 => 1]);                                   // đang xử lý
        $c = $this->place(User::factory()->create(), [6 => 1]);
        app(OrderService::class)->cancel($c, ActorType::Customer, $c->user, 'Đổi ý');        // đã hủy

        $res = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(['placed' => 4, 'completed' => 2, 'processing' => 1, 'returned' => 0, 'cancelled' => 1], $res->viewData('rates'));

        $pay = array_column($res->viewData('byPayment'), null, 'label');
        $this->assertSame([$this->net($cod), 1], [$pay['COD (tiền mặt)']['value'], $pay['COD (tiền mặt)']['orders']]);
        $this->assertSame([$this->net($bank), 1], [$pay['Chuyển khoản']['value'], $pay['Chuyển khoản']['orders']]);
        $this->assertSame(0, $pay['VNPay']['value']);
        $this->assertSame($res->viewData('stats')['cur']['revenue'], array_sum(array_column($res->viewData('byPayment'), 'value')));   // = thẻ doanh thu

        $html = $res->getContent();
        foreach (['order-status', 'by-category', 'by-payment'] as $id) {
            $this->assertStringContainsString('class="gg-donut" data-testid="'.$id.'"', $html);
        }
        $this->assertStringContainsString('conic-gradient(', $html);                         // CDN lỗi vẫn có vòng tròn
        $this->assertStringContainsString('50,0%', $html);                                    // chú thích ghi % (2/4 đơn hoàn thành)
        $this->assertStringNotContainsString('>VNPay<', $html);                               // phần bằng 0 không hiện
    }

    public function test_category_colour_follows_the_category_not_its_rank(): void
    {
        $this->deliver($this->place(cart: [1 => 1]));
        $cat = Plant::find(1)->category_id;
        $expected = DashboardController::PALETTE[DB::table('categories')->where('id', '<', $cat)->count()];
        $colour = function (array $query) use ($cat): string {
            $rows = $this->actingAs($this->admin())->get(route('admin.dashboard', $query))->viewData('byCategory');
            $name = DB::table('categories')->where('id', $cat)->value('name');

            return collect($rows)->firstWhere('name', $name)['color'];
        };
        $this->assertSame($expected, $colour([]));
        Carbon::setTestNow(now()->addDays(3));
        $this->deliver($this->place(User::factory()->create(), [Plant::where('category_id', '<>', $cat)->value('id') => 5]));   // danh mục khác vượt lên
        $this->assertSame($expected, $colour(['ky' => '7']));
    }

    public function test_dashboard_without_orders_shows_empty_states(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Chưa có đơn đặt trong kỳ.')->assertDontSee('class="gg-donut"', false);
    }

    public function test_editor_preview_renders_safely_and_saves_nothing(): void
    {
        $before = Article::count();
        $this->actingAs(User::factory()->staff()->create(['permissions' => ['content']]));
        $html = $this->postJson(route('admin.articles.preview'), [
            'content' => "## Cách tưới\n\n![Chậu cây](/storage/articles/2026/11/a.png)\n\n<script>alert(1)</script>\n\n<b onclick=\"x()\">đậm</b> [bấm](javascript:alert(1))",
        ])->assertOk()->json('html');
        $this->assertStringContainsString('<h2>Cách tưới</h2>', $html);
        $this->assertStringContainsString('<img loading="lazy" class="img-fluid rounded-3" src="/storage/articles/2026/11/a.png" alt="Chậu cây"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<b', $html);                                     // HTML gõ tay bị bỏ
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame($before, Article::count());
        $this->postJson(route('admin.articles.preview'), ['content' => str_repeat('a', 20001)])->assertStatus(422);

        $this->actingAs(User::factory()->staff()->create(['permissions' => ['orders']]))
            ->postJson(route('admin.articles.preview'), ['content' => 'x'])->assertForbidden();
        $this->actingAs($this->customer())->postJson(route('admin.articles.preview'), ['content' => 'x'])->assertForbidden();
    }

    public function test_editor_has_preview_button_and_cover_preview(): void
    {
        $this->actingAs($this->admin())->get(route('admin.articles.create'))->assertOk()
            ->assertSee('data-preview-url="'.route('admin.articles.preview').'"', false)
            ->assertSee('data-md-preview', false)->assertSee('data-editor-preview', false)->assertSee('data-cover-preview', false);
    }

    public function test_home_hot_products_slide_sideways_and_slider_has_pause_button(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('data-gg-carousel', $html);
        $this->assertSame((int) config('shop.home.hot_limit'), substr_count($html, 'class="gg-carousel-item"'));
        $this->assertStringNotContainsString('col-6 col-md-4 col-lg-3">', substr($html, (int) strpos($html, 'data-testid="hot-plants"'), 400));
        $this->assertStringContainsString('data-toggle aria-pressed="false" aria-label="Tạm dừng tự chạy"', $html);
    }
}

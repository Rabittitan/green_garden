<?php

namespace Tests\Feature\Http;

use App\Models\Order;
use App\Models\Plant;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ReviewService;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Góp ý chủ shop 24/11 (đợt 1): chi tiêu khách, ngưỡng nhập, đánh giá trong "Đơn hàng của tôi", giá vốn trên form cây. */
class OwnerFeedbackBatch1Test extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function delivered(?User $user = null, array $cart = [1 => 1]): Order
    {
        $orders = app(OrderService::class);
        $o = $this->place($user, $cart);
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'B1'.$o->id, $this->admin());

        return $orders->complete($o, $this->admin());
    }

    /** Lỗi chủ shop báo: "mua 10 đơn mà tổng chi tiêu = 0" — cột cũ là chi tiêu XÉT HẠNG (chốt sau 7 ngày). */
    public function test_customer_list_shows_purchases_right_away_and_tier_spend_separately(): void
    {
        $done = $this->delivered(cart: [1 => 2]);
        $this->place(cart: [3 => 1]);                                                       // chưa hoàn thành: không tính "đã mua"
        $this->assertSame(0, $this->customer()->refresh()->lifetime_spend);                  // chưa qua 7 ngày

        $html = $this->actingAs($this->admin())->get(route('admin.customers.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Đã mua', $html);
        $this->assertStringContainsString('Chi tiêu xét hạng', $html);
        $this->assertStringContainsString('1 / 2', $html);                                    // đơn hoàn thành / tổng
        $this->assertStringContainsString(Format::money($done->paid_amount), $html);
        $this->get(route('admin.customers.show', $this->customer()))->assertOk()
            ->assertSee('Đã mua (đơn hoàn thành)')->assertSee(Format::money($done->paid_amount));
    }

    public function test_reorder_point_is_not_prefilled_and_is_required(): void
    {
        $this->actingAs($this->admin())->get(route('admin.plants.create'))->assertOk()
            ->assertSee('name="reorder_point" min="0" required placeholder="VD: 10" value=""', false);
        $this->post(route('admin.plants.store'), ['category_id' => 1, 'name' => 'Cây X', 'price' => 1000, 'light' => 'ua_bong', 'element' => 'kim',
            'opening_stock' => 1, 'cost_price' => 500])->assertSessionHasErrors('reorder_point');
        $this->get(route('admin.plants.edit', 1))->assertSee('value="'.Plant::find(1)->reorder_point.'"', false)
            ->assertSee('Giá vốn bình quân')->assertSee('Lãi gộp mỗi cây');
    }

    public function test_customer_reviews_from_my_orders_tab(): void
    {
        $this->delivered(cart: [1 => 1, 3 => 1]);
        $this->actingAs($this->customer());
        $page = $this->get(route('orders.index', ['tab' => 'cho-danh-gia']))->assertOk()->getContent();
        $this->assertSame(2, substr_count($page, 'class="gg-panel gg-panel-body mb-3 gg-review-row"'));
        $this->assertStringContainsString('Chờ đánh giá <span class="badge bg-danger ms-1">2</span>', $page);

        $this->from(route('orders.index', ['tab' => 'cho-danh-gia']))
            ->post(route('reviews.store', 1), ['rating' => 4, 'content' => 'Cây đẹp'])
            ->assertRedirect(route('orders.index', ['tab' => 'cho-danh-gia']))->assertSessionHas('success');
        $page = $this->get(route('orders.index', ['tab' => 'cho-danh-gia']))->getContent();
        $this->assertSame(1, substr_count($page, 'gg-review-row'));                           // còn 1 cây
        $this->assertSame([4, 1], [Plant::find(1)->rating_sum, Plant::find(1)->rating_count]);

        app(ReviewService::class)->submit($this->customer(), 3, 5, null, 'Tốt');
        $this->get(route('orders.index', ['tab' => 'cho-danh-gia']))->assertSee('Không còn cây nào chờ đánh giá.');
        $this->get(route('orders.index'))->assertOk()->assertSee('Mã đơn');                    // tab mặc định vẫn là danh sách đơn
    }

    public function test_pending_or_other_customers_orders_are_not_offered_for_review(): void
    {
        $this->place(cart: [1 => 1]);                                                        // chưa giao
        $this->delivered(User::factory()->create(), [3 => 1]);                               // đơn của người khác
        $this->actingAs($this->customer())->get(route('orders.index', ['tab' => 'cho-danh-gia']))
            ->assertSee('Không còn cây nào chờ đánh giá.');
    }
}

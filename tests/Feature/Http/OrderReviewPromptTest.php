<?php

namespace Tests\Feature\Http;

use App\Models\Order;
use App\Models\Plant;
use App\Notifications\OrderCompleted;
use App\Services\OrderService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** K11: đơn đã giao thành công -> trang đơn mời khách đánh giá từng cây, đánh giá đăng ngay. */
class OrderReviewPromptTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function delivered(): Order
    {
        $orders = app(OrderService::class);
        $o = $this->place(cart: [1 => 1, 3 => 1]);
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'RV'.$o->id, $this->admin());

        return $orders->complete($o, $this->admin());
    }

    public function test_completed_order_links_each_plant_to_its_review_form(): void
    {
        $order = $this->delivered();
        $page = $this->actingAs($this->customer())->get(route('orders.show', $order))->assertOk();
        $page->assertSee('đánh giá cây bạn đã nhận', false)
            ->assertSee('href="'.route('plants.show', 1).'#reviews"', false)
            ->assertSee('href="'.route('plants.show', 3).'#reviews"', false);
        $this->assertSame(2, substr_count($page->getContent(), 'Đánh giá cây này'));

        // Đánh giá 1 cây: cây đó chuyển sang "Đã đánh giá", cây kia vẫn mời
        app(ReviewService::class)->submit($this->customer(), 1, 4, null, 'Ổn');
        $page = $this->get(route('orders.show', $order));
        $this->assertSame(1, substr_count($page->getContent(), 'Đánh giá cây này'));
        $page->assertSee('★★★★</span> Đã đánh giá', false)->assertSee('đánh giá cây bạn đã nhận', false);

        // Đánh giá đủ: hết lời nhắc
        app(ReviewService::class)->submit($this->customer(), 3, 5, null, 'Đẹp');
        $this->get(route('orders.show', $order))->assertDontSee('Đánh giá cây này')->assertDontSee('đánh giá cây bạn đã nhận', false);
    }

    public function test_no_review_prompt_before_delivery_or_for_discontinued_plants(): void
    {
        $pending = $this->place(cart: [1 => 1]);
        $this->actingAs($this->customer())->get(route('orders.show', $pending))->assertOk()
            ->assertDontSee('Đánh giá cây này')->assertDontSee('#reviews', false);

        $order = $this->delivered();
        Plant::whereKey(3)->update(['is_active' => false]);                  // cây ngừng bán: trang cây 404 -> không đưa link
        Plant::find(1)?->delete();                                            // cây đã xóa mềm: cũng không
        $this->get(route('orders.show', $order))->assertOk()
            ->assertDontSee('Đánh giá cây này')->assertDontSee('#reviews', false)->assertDontSee('đánh giá cây bạn đã nhận', false);
    }

    public function test_completed_order_page_stays_within_query_budget(): void
    {
        $order = $this->delivered();
        app(ReviewService::class)->submit($this->customer(), 1, 5, null, 'Tốt');
        $this->actingAs($this->customer());
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $this->get(route('orders.show', $order))->assertOk();
        $this->assertLessThanOrEqual(15, $n);
    }

    public function test_completed_email_invites_a_review(): void
    {
        $order = $this->delivered();
        $mail = (new OrderCompleted($order))->toMail($order->user);
        $this->assertStringContainsString('Đánh giá cây này', implode("\n", $mail->introLines));
    }
}

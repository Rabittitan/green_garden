<?php

namespace Tests\Feature\Http;

use App\Enums\ActorType;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * K10: biểu đồ doanh thu trên dashboard. Số liệu theo ngày phải dùng ĐÚNG công thức của thẻ "doanh thu"
 * (cộng mọi ngày = doanh thu lũy kế), chia ngày theo giờ Việt Nam, và kỳ trước tách riêng để so sánh.
 */
class DashboardTrendTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Đặt + giao xong 1 đơn, mốc hoàn thành = $completedAtUtc. */
    private function completedAt(string $completedAtUtc, array $cart = [1 => 1]): Order
    {
        $orders = app(OrderService::class);
        Carbon::setTestNow(Carbon::parse($completedAtUtc, 'UTC')->subHours(2));
        $o = $this->place(cart: $cart);
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'T'.$o->id, $this->admin());
        Carbon::setTestNow(Carbon::parse($completedAtUtc, 'UTC'));

        return $orders->complete($o, $this->admin());
    }

    private function net(Order $o): int
    {
        $o->refresh();

        return max(0, $o->subtotal - $o->member_discount - $o->coupon_discount - $o->refunded_amount);
    }

    /** @return array<string, mixed> */
    private function dashboard(array $query = []): array
    {
        $res = $this->actingAs($this->admin())->get(route('admin.dashboard', $query))->assertOk();
        $data = [];
        foreach (['trend', 'stats', 'rates', 'byCategory', 'topPlants', 'topCustomers'] as $k) {
            $data[$k] = $res->viewData($k);
        }
        $data['html'] = $res->getContent();

        return $data;
    }

    public function test_daily_series_uses_vietnam_days_and_matches_revenue_card(): void
    {
        $late = $this->completedAt('2026-11-21 16:30:00', [1 => 2]);        // 23:30 ngày 21/11 giờ VN
        $early = $this->completedAt('2026-11-21 18:30:00', [3 => 1]);       // 01:30 ngày 22/11 giờ VN
        $prev = $this->completedAt('2026-10-10 05:00:00');                  // thuộc 30 ngày liền trước
        $firstDay = $this->completedAt('2026-10-24 03:00:00');              // ngày đầu của kỳ này: chỉ tính vào kỳ này
        $prevEdge = $this->completedAt('2026-09-23 18:00:00');              // 01:00 ngày 24/09 giờ VN = ngày đầu kỳ trước
        $old = $this->completedAt('2026-08-01 05:00:00');                   // quá 60 ngày: không vào biểu đồ
        $this->place(cart: [5 => 1]);                                       // chưa hoàn thành: không tính

        Carbon::setTestNow(Carbon::parse('2026-11-22 03:00:00', 'UTC'));    // 10:00 ngày 22/11 giờ VN
        ['trend' => $t, 'stats' => $stats] = $this->dashboard();

        $this->assertCount(DashboardController::TREND_DAYS, $t['days']);
        $this->assertSame(['2026-10-24', '2026-11-22'], [$t['days'][0]['date'], end($t['days'])['date']]);
        $byDate = array_column($t['days'], null, 'date');
        $this->assertSame([$this->net($early), 1], [$byDate['2026-11-22']['revenue'], $byDate['2026-11-22']['orders']]);
        $this->assertSame([$this->net($late), 1], [$byDate['2026-11-21']['revenue'], $byDate['2026-11-21']['orders']]);
        $this->assertSame(0, $byDate['2026-11-20']['revenue']);                     // ngày không có đơn vẫn có mặt

        $this->assertSame(1, $byDate['2026-10-24']['orders']);
        $this->assertSame([$this->net($early) + $this->net($late) + $this->net($firstDay), 3], [$t['total'], $t['orders']]);
        $this->assertSame([$this->net($prev) + $this->net($prevEdge), 2], [$t['previous'], $t['previousOrders']]);
        // Cùng công thức với thẻ doanh thu: biểu đồ = thẻ kỳ này, phần kỳ trước = thẻ kỳ trước; đơn quá 60 ngày không vào đâu
        $this->assertSame([$t['total'], $t['orders']], [$stats['cur']['revenue'], $stats['cur']['orders']]);
        $this->assertSame([$t['previous'], $t['previousOrders']], [$stats['prev']['revenue'], $stats['prev']['orders']]);
        $this->assertGreaterThan(0, $this->net($old));
    }

    public function test_refund_after_delivery_lowers_that_day_like_the_revenue_card(): void
    {
        $o = $this->completedAt('2026-11-20 03:00:00');
        Carbon::setTestNow(Carbon::parse('2026-11-22 03:00:00', 'UTC'));
        $before = $this->dashboard()['trend']['total'];
        $payments = app(PaymentService::class);                                  // hoàn một phần qua luồng thật (DB chặn ghi tay)
        $payments->confirmRefund($payments->requestRefund($o->refresh(), 10000, $this->admin(), 'Cây dập lá'), $this->admin(), 'RF-DASH');
        ['trend' => $t, 'stats' => $stats] = $this->dashboard();
        $this->assertSame($before - 10000, $t['total']);
        $this->assertSame($stats['cur']['revenue'], $t['total']);
    }

    public function test_comparison_text_and_table_fallback(): void
    {
        $this->completedAt('2026-10-15 03:00:00', [1 => 1]);                    // kỳ trước: 1 cây
        $this->completedAt('2026-11-20 03:00:00', [1 => 3]);                    // kỳ này: 3 cây -> tăng
        Carbon::setTestNow(Carbon::parse('2026-11-22 03:00:00', 'UTC'));
        ['trend' => $t, 'html' => $html] = $this->dashboard();

        $pct = number_format(round(($t['total'] - $t['previous']) * 100 / $t['previous'], 1), 1, ',', '.');
        $this->assertStringContainsString('Tăng '.$pct.'%', $html);
        // Biểu đồ có bảng số liệu dự phòng (CDN lỗi / trình đọc màn hình) + dữ liệu JSON, không chèn biến vào JS
        $this->assertStringContainsString('Xem bảng số liệu theo ngày', $html);
        $this->assertStringContainsString('<script type="application/json" id="revenue-data">', $html);
        $this->assertStringContainsString('chart.js@4.5.1/dist/chart.umd.min.js', $html);
        $this->assertStringContainsString('data-testid="by-category"', $html);
        $this->assertStringContainsString('data-testid="top-plants"', $html);
        $this->assertStringContainsString('data-testid="top-customers"', $html);
        $this->assertStringNotContainsString('Việc cần làm', $html);                        // đã bỏ theo yêu cầu
        $this->assertStringNotContainsString('Đơn theo trạng thái', $html);
    }

    public function test_profit_categories_customers_and_rates(): void
    {
        $a = $this->completedAt('2026-11-20 03:00:00', [1 => 2, 5 => 1]);
        $b = $this->completedAt('2026-11-21 03:00:00', [1 => 1]);
        Carbon::setTestNow(Carbon::parse('2026-11-21 05:00:00', 'UTC'));
        $c = $this->place(User::factory()->create(), [3 => 1]);                                  // đặt trong kỳ rồi hủy
        app(OrderService::class)->cancel($c, ActorType::Customer, $c->user, 'Đổi ý');
        Carbon::setTestNow(Carbon::parse('2026-11-22 03:00:00', 'UTC'));
        $d = $this->dashboard();

        $cogs = (int) OrderItem::whereIn('order_id', [$a->id, $b->id])->get()->sum(fn ($i) => $i->unit_cost * $i->quantity);
        $revenue = $this->net($a) + $this->net($b);
        $this->assertSame(['revenue' => $revenue, 'cogs' => $cogs, 'profit' => $revenue - $cogs, 'orders' => 2,
            'aov' => intdiv($revenue, 2), 'shipping' => ($a->shipping_fee - $a->shipping_discount) + ($b->shipping_fee - $b->shipping_discount)], $d['stats']['cur']);

        // Theo danh mục: cộng lại = tổng giá trị dòng hàng của 2 đơn (không có hoàn tiền nên = doanh thu)
        $this->assertSame($revenue, array_sum(array_column($d['byCategory'], 'revenue')));
        $this->assertSame(['Cây Kim Tiền', 3], [$d['topPlants'][0]['name'], $d['topPlants'][0]['qty']]);
        $this->assertSame([$this->customer()->id, 2, $revenue], [$d['topCustomers'][0]['id'], $d['topCustomers'][0]['orders'], $d['topCustomers'][0]['revenue']]);
        $this->assertSame(1, $d['rates']['cancelled']);
        $this->assertGreaterThanOrEqual(1, $d['rates']['placed']);

        // Đổi kỳ: 7 ngày vẫn chứa 2 đơn; "tháng này" bắt đầu từ 01/11
        $this->assertSame(2, $this->dashboard(['ky' => '7'])['stats']['cur']['orders']);
        $month = $this->dashboard(['ky' => 'thang'])['trend']['days'];
        $this->assertSame(['2026-11-01', 22], [$month[0]['date'], count($month)]);
        $this->assertCount(DashboardController::TREND_DAYS, $this->dashboard(['ky' => 'bậy'])['trend']['days']);   // kỳ lạ -> mặc định
    }

    public function test_no_previous_revenue_shows_no_percentage(): void
    {
        $this->completedAt('2026-11-20 03:00:00');
        Carbon::setTestNow(Carbon::parse('2026-11-22 03:00:00', 'UTC'));
        $html = $this->dashboard()['html'];
        $this->assertStringNotContainsString('Tăng ', $html);                                // kỳ trước = 0: không có %
        $this->assertStringNotContainsString('Giảm ', $html);
    }
}

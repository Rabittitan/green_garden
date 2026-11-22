<?php

namespace Tests\Feature\Http;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Order;
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
    private function dashboard(): array
    {
        $res = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
        $data = [];
        foreach (['trend', 'revenue'] as $k) {
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
        ['trend' => $t, 'revenue' => $revenue] = $this->dashboard();

        $this->assertCount(DashboardController::TREND_DAYS, $t['days']);
        $this->assertSame(['2026-10-24', '2026-11-22'], [$t['days'][0]['date'], end($t['days'])['date']]);
        $byDate = array_column($t['days'], null, 'date');
        $this->assertSame([$this->net($early), 1], [$byDate['2026-11-22']['revenue'], $byDate['2026-11-22']['orders']]);
        $this->assertSame([$this->net($late), 1], [$byDate['2026-11-21']['revenue'], $byDate['2026-11-21']['orders']]);
        $this->assertSame(0, $byDate['2026-11-20']['revenue']);                     // ngày không có đơn vẫn có mặt

        $this->assertSame(1, $byDate['2026-10-24']['orders']);
        $this->assertSame([$this->net($early) + $this->net($late) + $this->net($firstDay), 3], [$t['total'], $t['orders']]);
        $this->assertSame([$this->net($prev) + $this->net($prevEdge), 2], [$t['previous'], $t['previousOrders']]);
        // Cùng công thức với thẻ doanh thu: kỳ này + kỳ trước + phần cũ hơn = doanh thu lũy kế
        $this->assertSame($revenue, $t['total'] + $t['previous'] + $this->net($old));
    }

    public function test_refund_after_delivery_lowers_that_day_like_the_revenue_card(): void
    {
        $o = $this->completedAt('2026-11-20 03:00:00');
        Carbon::setTestNow(Carbon::parse('2026-11-22 03:00:00', 'UTC'));
        $before = $this->dashboard()['trend']['total'];
        $payments = app(PaymentService::class);                                  // hoàn một phần qua luồng thật (DB chặn ghi tay)
        $payments->confirmRefund($payments->requestRefund($o->refresh(), 10000, $this->admin(), 'Cây dập lá'), $this->admin(), 'RF-DASH');
        ['trend' => $t, 'revenue' => $revenue] = $this->dashboard();
        $this->assertSame($before - 10000, $t['total']);
        $this->assertSame($revenue, $t['total']);
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
        $this->assertStringContainsString('data-testid="status-meters"', $html);
        $this->assertStringContainsString('data-testid="top-plants"', $html);
    }

    public function test_no_previous_revenue_shows_no_percentage(): void
    {
        $this->completedAt('2026-11-20 03:00:00');
        Carbon::setTestNow(Carbon::parse('2026-11-22 03:00:00', 'UTC'));
        $html = $this->dashboard()['html'];
        $this->assertStringContainsString('Chưa có doanh thu 30 ngày trước đó để so sánh', $html);
        $this->assertStringNotContainsString('Tăng ', $html);
    }
}

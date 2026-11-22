<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Doanh thu hàng hóa thuần của 1 đơn: tiền hàng sau mọi giảm giá (không gồm phí ship),
     * trừ phần đã hoàn của chính đơn đó (khiếu nại cây chết...), không âm. Dùng chung cho thẻ tổng và biểu đồ theo ngày.
     */
    private const NET_GOODS = 'GREATEST(0, CAST(subtotal AS SIGNED) - CAST(member_discount AS SIGNED)'
        .' - CAST(coupon_discount AS SIGNED) - CAST(refunded_amount AS SIGNED))';

    /** Số ngày của biểu đồ doanh thu (so sánh với cùng số ngày liền trước). */
    public const TREND_DAYS = 30;

    public function index(): View
    {
        $completed = Order::status(OrderStatus::Completed);

        return view('admin.dashboard', [
            // Doanh thu hàng hóa thuần của mọi đơn đã hoàn thành
            'revenue' => (int) (clone $completed)->sum(DB::raw(self::NET_GOODS)),
            'trend' => $this->trend(),
            'refunded' => (int) Order::sum('refunded_amount'),
            // Tiền thực thu = đã thu - đã hoàn (mọi đơn)
            'cashIn' => (int) Order::sum(DB::raw('CAST(paid_amount AS SIGNED) - CAST(refunded_amount AS SIGNED)')),
            'cogs' => (int) OrderItem::whereHas('order', fn ($q) => $q->where('status', OrderStatus::Completed->value))
                ->sum(DB::raw('unit_cost * quantity')),
            'counts' => Order::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'needRefund' => Order::where('payment_status', PaymentStatus::RefundPending->value)->count(),
            'topPlants' => OrderItem::select('plant_id', 'plant_name', DB::raw('SUM(quantity) as sold'))
                ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Completed->value))
                ->groupBy('plant_id', 'plant_name')->orderByDesc('sold')->take(5)->get(),
            'lowStock' => Plant::active()->needsReorder()->orderBy('stock')->take(10)->get(),
            'latestOrders' => Order::latest('id')->take(8)->get(),
        ]);
    }

    /**
     * Doanh thu thuần + số đơn hoàn thành theo NGÀY GIỜ VIỆT NAM, cho TREND_DAYS ngày gần nhất (tính cả hôm nay)
     * và tổng của TREND_DAYS ngày liền trước để so sánh. Một câu SQL; ngày không có đơn vẫn có mặt (giá trị 0).
     * Mốc ngày là ngày hoàn thành đơn (completed_at, lưu UTC) đổi sang giờ hiển thị của shop.
     *
     * @return array{days: list<array{date: string, label: string, revenue: int, orders: int}>, total: int, orders: int, previous: int, previousOrders: int}
     */
    private function trend(): array
    {
        $tz = (string) config('shop.display_timezone');
        $today = now($tz)->startOfDay();
        $from = $today->copy()->subDays(self::TREND_DAYS - 1);              // ngày đầu kỳ này
        $prevFrom = $from->copy()->subDays(self::TREND_DAYS);               // ngày đầu kỳ trước
        $offset = $today->format('P');                                      // vd +07:00 (Việt Nam không đổi giờ theo mùa)

        $rows = Order::status(OrderStatus::Completed)
            ->where('completed_at', '>=', $prevFrom->copy()->utc())
            ->selectRaw("DATE(CONVERT_TZ(completed_at, '+00:00', ?)) as d, SUM(".self::NET_GOODS.') as revenue, COUNT(*) as n', [$offset])
            ->groupBy('d')->toBase()->get()->keyBy('d');

        $days = [];
        for ($day = $from->copy(); $day->lte($today); $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $days[] = ['date' => $day->toDateString(), 'label' => $day->format('d/m'),
                'revenue' => (int) ($row->revenue ?? 0), 'orders' => (int) ($row->n ?? 0)];
        }
        $previous = $rows->filter(fn ($r, $d) => (string) $d < $from->toDateString());

        return [
            'days' => $days,
            'total' => array_sum(array_column($days, 'revenue')),
            'orders' => array_sum(array_column($days, 'orders')),
            'previous' => (int) $previous->sum('revenue'),
            'previousOrders' => (int) $previous->sum('n'),
        ];
    }
}

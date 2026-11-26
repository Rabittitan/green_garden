<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Thống kê kinh doanh theo KỲ (7 / 30 / 90 ngày, tháng này), so với kỳ dài bằng nhau liền trước.
 * Mọi số tiền "doanh thu" dùng chung NET_GOODS và mốc ngày hoàn thành đơn (completed_at) theo giờ hiển thị của shop.
 * Chỉ đọc; ~8 câu SQL không tăng theo số đơn.
 */
class DashboardController extends Controller
{
    /**
     * Doanh thu hàng hóa thuần của 1 đơn: tiền hàng sau mọi giảm giá (không gồm phí ship),
     * trừ phần đã hoàn của chính đơn đó (khiếu nại cây chết...), không âm. Dùng chung cho thẻ, biểu đồ, top khách.
     */
    private const NET_GOODS = 'GREATEST(0, CAST(subtotal AS SIGNED) - CAST(member_discount AS SIGNED)'
        .' - CAST(coupon_discount AS SIGNED) - CAST(refunded_amount AS SIGNED))';

    /** Giá trị dòng hàng sau giảm giá phân bổ (dùng cho doanh thu theo danh mục / theo cây). */
    private const LINE_NET = '(CAST(order_items.line_subtotal AS SIGNED) - CAST(order_items.member_discount AS SIGNED) - CAST(order_items.coupon_discount AS SIGNED))';

    /** Kỳ xem được: khóa trên URL (?ky=) => [nhãn, số ngày | null = từ đầu tháng] */
    public const PERIODS = ['7' => ['7 ngày', 7], '30' => ['30 ngày', 30], '90' => ['90 ngày', 90], 'thang' => ['Tháng này', null]];

    /** Kỳ mặc định (cũng là độ dài biểu đồ khi mở trang). */
    public const TREND_DAYS = 30;

    /**
     * Màu biểu đồ tròn, gán theo THỨ TỰ cố định (phần lớn nhất màu 1...). Bộ 6 màu đã kiểm tra: các cặp màu kề nhau
     * phân biệt được cả với người mù màu đỏ-lục; vài màu nhạt trên nền trắng nên chú thích luôn ghi tên + số + %.
     */
    public const PALETTE = ['#257de0', '#f47230', '#00b894', '#f2a600', '#ee7fac', '#008a17'];

    public const OTHER_COLOR = '#a3aca6';

    /** Màu tình trạng đơn: xanh lá = xong, xanh dương = đang xử lý, cam = trả hàng, đỏ = hủy. */
    public const STATUS_COLORS = ['completed' => '#0aac23', 'processing' => '#257de0', 'returned' => '#f18a59', 'cancelled' => '#dc3c3d'];

    /** Trang đầu của từng nhóm quyền: nhân viên không có quyền "Thống kê" được đưa tới mục đầu tiên mình dùng được. */
    public const LANDING = [
        'orders' => 'admin.orders.index', 'catalog' => 'admin.plants.index', 'inventory' => 'admin.inventory.index',
        'purchasing' => 'admin.purchase-requests.index', 'reviews' => 'admin.reviews.index', 'content' => 'admin.articles.index', 'flash_sales' => 'admin.flash-sales.index',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission(StaffPermission::Reports)) {
            foreach (self::LANDING as $perm => $route) {
                if ($user->hasPermission($perm)) {
                    return redirect()->route($route);
                }
            }

            return view('admin.no-permission');
        }

        $key = array_key_exists((string) $request->query('ky'), self::PERIODS) ? (string) $request->query('ky') : (string) self::TREND_DAYS;
        $tz = (string) config('shop.display_timezone');
        $today = now($tz)->startOfDay();
        $days = self::PERIODS[$key][1] ?? ((int) $today->day);
        $from = $today->copy()->subDays($days - 1);                          // ngày đầu kỳ này (giờ VN)
        $prevFrom = $from->copy()->subDays($days);                          // kỳ trước dài bằng kỳ này, liền trước
        $range = ['from' => $from->copy()->utc(), 'prev' => $prevFrom->copy()->utc(), 'to' => $today->copy()->addDay()->utc()];

        return view('admin.dashboard', [
            'period' => $key, 'periodLabel' => self::PERIODS[$key][0], 'days' => $days,
            'stats' => $this->stats($range),
            'trend' => $this->trend($from, $today, $range, $tz),
            'rates' => $this->rates($range),
            'byCategory' => $this->byCategory($range),
            'byPayment' => $this->byPayment($range),
            'topPlants' => $this->topPlants($range),
            'topCustomers' => $this->topCustomers($range),
        ]);
    }

    /**
     * Thẻ số liệu kỳ này + kỳ trước (đơn HOÀN THÀNH theo ngày hoàn thành): doanh thu, giá vốn, lợi nhuận gộp,
     * số đơn, giá trị TB/đơn, phí ship thu được.
     *
     * @param  array{from: Carbon, prev: Carbon, to: Carbon}  $r
     * @return array{cur: array<string, int>, prev: array<string, int>}
     */
    private function stats(array $r): array
    {
        $row = Order::status(OrderStatus::Completed)->where('completed_at', '>=', $r['prev'])->where('completed_at', '<', $r['to'])
            ->selectRaw('SUM(CASE WHEN completed_at >= ? THEN '.self::NET_GOODS.' ELSE 0 END) AS cur_rev, '
                .'SUM(CASE WHEN completed_at < ? THEN '.self::NET_GOODS.' ELSE 0 END) AS prev_rev, '
                .'SUM(completed_at >= ?) AS cur_n, SUM(completed_at < ?) AS prev_n, '
                .'SUM(CASE WHEN completed_at >= ? THEN shipping_fee - shipping_discount ELSE 0 END) AS cur_ship, '
                .'SUM(CASE WHEN completed_at < ? THEN shipping_fee - shipping_discount ELSE 0 END) AS prev_ship',
                array_fill(0, 6, $r['from']))->toBase()->first();
        $cogs = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed->value)->where('orders.completed_at', '>=', $r['prev'])->where('orders.completed_at', '<', $r['to'])
            ->selectRaw('SUM(CASE WHEN orders.completed_at >= ? THEN order_items.unit_cost * order_items.quantity ELSE 0 END) AS cur, '
                .'SUM(CASE WHEN orders.completed_at < ? THEN order_items.unit_cost * order_items.quantity ELSE 0 END) AS prev', [$r['from'], $r['from']])
            ->toBase()->first();

        $make = function (string $p) use ($row, $cogs) {
            $revenue = (int) ($row->{$p.'_rev'} ?? 0);
            $orders = (int) ($row->{$p.'_n'} ?? 0);
            $cost = (int) ($cogs->{$p} ?? 0);

            return ['revenue' => $revenue, 'cogs' => $cost, 'profit' => $revenue - $cost, 'orders' => $orders,
                'aov' => $orders ? intdiv($revenue, $orders) : 0, 'shipping' => (int) ($row->{$p.'_ship'} ?? 0)];
        };

        return ['cur' => $make('cur'), 'prev' => $make('prev')];
    }

    /**
     * Doanh thu + số đơn hoàn thành theo NGÀY GIỜ VIỆT NAM trong kỳ (ngày không có đơn vẫn có mặt, giá trị 0),
     * kèm tổng kỳ này / kỳ trước để so sánh. Một câu SQL.
     *
     * @param  array{from: Carbon, prev: Carbon, to: Carbon}  $r
     * @return array{days: list<array{date: string, label: string, revenue: int, orders: int}>, total: int, orders: int, previous: int, previousOrders: int}
     */
    private function trend(Carbon $from, Carbon $today, array $r, string $tz): array
    {
        $offset = $today->format('P');                                      // vd +07:00 (Việt Nam không đổi giờ theo mùa)
        $rows = Order::status(OrderStatus::Completed)
            ->where('completed_at', '>=', $r['prev'])->where('completed_at', '<', $r['to'])
            ->selectRaw("DATE(CONVERT_TZ(completed_at, '+00:00', ?)) as d, SUM(".self::NET_GOODS.') as revenue, COUNT(*) as n', [$offset])
            ->groupBy('d')->toBase()->get()->keyBy('d');

        $days = [];
        for ($day = $from->copy(); $day->lte($today); $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $days[] = ['date' => $day->toDateString(), 'label' => $day->format('d/m'),
                'revenue' => (int) ($row->revenue ?? 0), 'orders' => (int) ($row->n ?? 0)];
        }
        $previous = $rows->filter(fn ($row, $d) => (string) $d < $from->toDateString());

        return [
            'days' => $days,
            'total' => array_sum(array_column($days, 'revenue')),
            'orders' => array_sum(array_column($days, 'orders')),
            'previous' => (int) $previous->sum('revenue'),
            'previousOrders' => (int) $previous->sum('n'),
        ];
    }

    /**
     * Đơn ĐẶT trong kỳ theo tình trạng hiện tại (biểu đồ tròn "Tình trạng đơn") + tỉ lệ hủy / trả hàng.
     * completed + processing + returned + cancelled = placed.
     *
     * @param  array{from: Carbon, prev: Carbon, to: Carbon}  $r
     * @return array{placed: int, completed: int, processing: int, returned: int, cancelled: int}
     */
    private function rates(array $r): array
    {
        $row = Order::where('placed_at', '>=', $r['from'])->where('placed_at', '<', $r['to'])
            ->selectRaw('COUNT(*) AS placed, SUM(status = ?) AS completed, SUM(status = ?) AS cancelled, SUM(status IN (?, ?)) AS returned', [
                OrderStatus::Completed->value, OrderStatus::Cancelled->value, OrderStatus::Returning->value, OrderStatus::Returned->value,
            ])
            ->toBase()->first();
        $placed = (int) ($row->placed ?? 0);
        $completed = (int) ($row->completed ?? 0);
        $cancelled = (int) ($row->cancelled ?? 0);
        $returned = (int) ($row->returned ?? 0);

        return ['placed' => $placed, 'completed' => $completed, 'processing' => $placed - $completed - $cancelled - $returned,
            'returned' => $returned, 'cancelled' => $cancelled];
    }

    /**
     * Giá trị hàng bán theo danh mục (dòng hàng sau giảm giá, đơn hoàn thành trong kỳ).
     * Màu đi theo DANH MỤC (thứ tự id), không theo hạng: đổi kỳ thống kê không đổi màu. Danh mục thứ 7 trở đi màu "Khác".
     *
     * @param  array{from: Carbon, prev: Carbon, to: Carbon}  $r
     * @return list<array{name: string, revenue: int, qty: int, color: string}>
     */
    private function byCategory(array $r): array
    {
        $palette = Category::orderBy('id')->limit(count(self::PALETTE))->pluck('id')->flip()->all();

        return $this->completedLines($r)
            ->join('plants', 'plants.id', '=', 'order_items.plant_id')
            ->join('categories', 'categories.id', '=', 'plants.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id AS id, categories.name AS name, SUM('.self::LINE_NET.') AS revenue, SUM(order_items.quantity) AS qty')
            ->orderByDesc('revenue')->toBase()->get()
            // Xếp theo thứ tự màu (= thứ tự danh mục): hai phần cạnh nhau trên vòng luôn là cặp màu đã kiểm tra dễ phân biệt
            ->sortBy(fn ($x) => $palette[(int) $x->id] ?? count(self::PALETTE))
            ->map(fn ($x) => ['name' => (string) $x->name, 'revenue' => (int) $x->revenue, 'qty' => (int) $x->qty,
                'color' => self::PALETTE[$palette[(int) $x->id] ?? -1] ?? self::OTHER_COLOR])->values()->all();
    }

    /**
     * Đơn hoàn thành trong kỳ theo cách thanh toán: doanh thu hàng hóa (cùng công thức thẻ doanh thu) + số đơn.
     *
     * @param  array{from: Carbon, prev: Carbon, to: Carbon}  $r
     * @return list<array{label: string, value: int, orders: int, color: string}>
     */
    private function byPayment(array $r): array
    {
        $rows = Order::status(OrderStatus::Completed)->where('completed_at', '>=', $r['from'])->where('completed_at', '<', $r['to'])
            ->groupBy('payment_method')->selectRaw('payment_method, SUM('.self::NET_GOODS.') AS revenue, COUNT(*) AS n')
            ->toBase()->get()->keyBy('payment_method');
        $out = [];
        foreach (PaymentMethod::cases() as $i => $m) {                       // màu cố định theo cách thanh toán
            $row = $rows->get($m->value);
            $label = match ($m) {                                             // nhãn ngắn cho chú thích biểu đồ
                PaymentMethod::Cod => 'COD (tiền mặt)',
                PaymentMethod::Vnpay => 'VNPay',
                PaymentMethod::BankTransfer => 'Chuyển khoản',
            };
            $out[] = ['label' => $label, 'value' => (int) ($row->revenue ?? 0), 'orders' => (int) ($row->n ?? 0), 'color' => self::PALETTE[$i]];
        }

        return $out;
    }

    /**
     * Top 5 cây theo doanh thu, kèm lợi nhuận gộp (doanh thu dòng − giá vốn lúc bán).
     *
     * @param  array{from: Carbon, prev: Carbon, to: Carbon}  $r
     * @return list<array{name: string, revenue: int, profit: int, qty: int}>
     */
    private function topPlants(array $r): array
    {
        return $this->completedLines($r)
            ->groupBy('order_items.plant_id', 'order_items.plant_name')
            ->selectRaw('order_items.plant_name AS name, SUM('.self::LINE_NET.') AS revenue, '
                .'SUM('.self::LINE_NET.' - CAST(order_items.unit_cost * order_items.quantity AS SIGNED)) AS profit, SUM(order_items.quantity) AS qty')
            ->orderByDesc('revenue')->limit(5)->toBase()->get()
            ->map(fn ($x) => ['name' => (string) $x->name, 'revenue' => (int) $x->revenue, 'profit' => (int) $x->profit, 'qty' => (int) $x->qty])->values()->all();
    }

    /**
     * Top 5 khách theo doanh thu hàng hóa trong kỳ.
     *
     * @param  array{from: Carbon, prev: Carbon, to: Carbon}  $r
     * @return list<array{id: int, name: string, email: string, revenue: int, orders: int}>
     */
    private function topCustomers(array $r): array
    {
        return Order::status(OrderStatus::Completed)->where('completed_at', '>=', $r['from'])->where('completed_at', '<', $r['to'])
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->selectRaw('users.id AS id, users.name AS name, users.email AS email, SUM('.str_replace(['subtotal', 'member_discount', 'coupon_discount', 'refunded_amount'],
                ['orders.subtotal', 'orders.member_discount', 'orders.coupon_discount', 'orders.refunded_amount'], self::NET_GOODS).') AS revenue, COUNT(*) AS n')
            ->orderByDesc('revenue')->limit(5)->toBase()->get()
            ->map(fn ($x) => ['id' => (int) $x->id, 'name' => (string) $x->name, 'email' => (string) $x->email, 'revenue' => (int) $x->revenue, 'orders' => (int) $x->n])
            ->values()->all();
    }

    /**
     * Dòng hàng của đơn hoàn thành trong kỳ này.
     *
     * @param  array{from: Carbon, prev: Carbon, to: Carbon}  $r
     * @return Builder<OrderItem>
     */
    private function completedLines(array $r)
    {
        return OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->where('orders.completed_at', '>=', $r['from'])->where('orders.completed_at', '<', $r['to']);
    }
}

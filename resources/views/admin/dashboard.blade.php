@extends('layouts.admin')
@section('title', 'Thống kê')

@section('content')
@php
    use App\Enums\OrderStatus;
    use App\Http\Controllers\Admin\DashboardController;
    $days = DashboardController::TREND_DAYS;
    // So với kỳ trước: null khi kỳ trước chưa có doanh thu (không chia cho 0, không bịa % tăng)
    $delta = $trend['previous'] > 0 ? round(($trend['total'] - $trend['previous']) * 100 / $trend['previous'], 1) : null;
    $statusMax = max(1, (int) $counts->max());
    $topMax = max(1, (int) $topPlants->max('sold'));
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <span class="gg-eyebrow">Tổng quan</span>
        <h4 class="fw-bold mb-0">Thống kê kinh doanh</h4>
    </div>
    <span class="small text-muted"><i class="fa fa-clock"></i> Cập nhật @vndt(now())</span>
</div>

<div class="row g-3 mb-3">
    {{-- ===== DOANH THU THEO NGÀY ===== --}}
    <div class="col-xl-8">
        <div class="gg-panel gg-panel-body h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-2">
                <div>
                    <div class="gg-stat-label">Doanh thu hàng hóa {{ $days }} ngày qua</div>
                    <div class="gg-hero-value" data-testid="trend-total">@money($trend['total'])</div>
                    <div class="small">
                        @if($delta === null)
                            <span class="text-muted">Chưa có doanh thu {{ $days }} ngày trước đó để so sánh</span>
                        @else
                            <span class="gg-delta {{ $delta >= 0 ? 'up' : 'down' }}"><i class="fa fa-arrow-{{ $delta >= 0 ? 'up' : 'down' }}"></i>
                                {{ $delta >= 0 ? 'Tăng' : 'Giảm' }} {{ number_format(abs($delta), 1, ',', '.') }}%</span>
                            <span class="text-muted">so với {{ $days }} ngày trước đó (@money($trend['previous']))</span>
                        @endif
                    </div>
                </div>
                <div class="gg-mini-kpis">
                    <div><small>Đơn hoàn thành</small><strong>{{ $trend['orders'] }}</strong></div>
                    <div><small>Giá trị TB / đơn</small><strong>@money($trend['orders'] ? intdiv($trend['total'], $trend['orders']) : 0)</strong></div>
                </div>
            </div>
            <div class="gg-chart" id="revenue-chart-wrap">
                <canvas id="revenue-chart" role="img" aria-label="Biểu đồ doanh thu hàng hóa theo ngày, {{ $days }} ngày gần nhất"></canvas>
                <div class="gg-chart-fallback small text-muted">Không tải được thư viện biểu đồ. Xem số liệu ở bảng bên dưới.</div>
            </div>
            <details class="mt-2 small">
                <summary class="text-green">Xem bảng số liệu theo ngày</summary>
                <div class="table-responsive mt-2" style="max-height:260px">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Ngày</th><th class="text-end">Đơn hoàn thành</th><th class="text-end">Doanh thu</th></tr></thead>
                        <tbody>
                            @foreach(array_reverse($trend['days']) as $d)
                                <tr><td>{{ $d['label'] }}</td><td class="text-end">{{ $d['orders'] }}</td><td class="text-end">@money($d['revenue'])</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
            <p class="small text-muted mb-0 mt-2">Tính theo ngày giao thành công (giờ Việt Nam): tiền hàng sau giảm giá, trừ tiền đã hoàn, không gồm phí ship.</p>
        </div>
    </div>

    {{-- ===== VIỆC CẦN LÀM + ĐƠN THEO TRẠNG THÁI ===== --}}
    <div class="col-xl-4">
        <div class="gg-panel gg-panel-body mb-3">
            <div class="gg-stat-label mb-1"><i class="fa fa-list-check"></i> Việc cần làm</div>
            @php
                $todo = [
                    [route('admin.orders.index', ['status' => 'pending']), $counts['pending'] ?? 0, 'đơn chờ xác nhận'],
                    [route('admin.orders.index', ['status' => 'confirmed']), $counts['confirmed'] ?? 0, 'đơn chờ giao'],
                    [route('admin.orders.index', ['status' => 'returning']), $counts['returning'] ?? 0, 'đơn đang hoàn về'],
                    [route('admin.orders.index', ['payment_status' => 'refund_pending']), $needRefund, 'đơn chờ hoàn tiền'],
                ];
            @endphp
            <div class="gg-todo small">
                @foreach($todo as [$url, $n, $label])
                    <a href="{{ $url }}"><span>{{ $n }} {{ $label }}</span><span class="badge rounded-pill {{ $n > 0 ? 'bg-danger' : 'bg-light text-muted' }}">{{ $n }}</span></a>
                @endforeach
            </div>
        </div>
        <div class="gg-panel gg-panel-body">
            <div class="gg-stat-label mb-2"><i class="fa fa-layer-group"></i> Đơn theo trạng thái <span class="fw-normal">(mọi thời gian)</span></div>
            <ul class="gg-meters" data-testid="status-meters">
                @foreach(OrderStatus::cases() as $st)
                    @php $n = (int) ($counts[$st->value] ?? 0); @endphp
                    <li><a href="{{ route('admin.orders.index', ['status' => $st->value]) }}">
                        <span class="gg-meter-name">{{ $st->label() }}</span>
                        <span class="gg-meter-bar"><span style="width: {{ $n ? max(2, round($n * 100 / $statusMax)) : 0 }}%"></span></span>
                        <span class="gg-meter-value">{{ $n }}</span>
                    </a></li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

{{-- ===== SỐ LIỆU LŨY KẾ ===== --}}
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="gg-stat">
        <div class="gg-stat-icon" style="background:#e8f5e9;color:#2e7d32"><i class="fa fa-sack-dollar"></i></div>
        <div class="gg-stat-label">Doanh thu hàng hóa (lũy kế)</div><div class="gg-stat-value">@money($revenue)</div>
        <small>đơn hoàn thành, sau giảm giá và hoàn tiền, không gồm ship</small>
    </div></div>
    <div class="col-md-4"><div class="gg-stat">
        <div class="gg-stat-icon" style="background:#e3f2fd;color:#1565c0"><i class="fa fa-chart-line"></i></div>
        <div class="gg-stat-label">Lợi nhuận gộp (lũy kế)</div><div class="gg-stat-value">@money($revenue - $cogs)</div>
        <small>doanh thu − giá vốn lúc bán (@money($cogs))</small>
    </div></div>
    <div class="col-md-4"><div class="gg-stat">
        <div class="gg-stat-icon" style="background:#f3e5f5;color:#6a1b9a"><i class="fa fa-wallet"></i></div>
        <div class="gg-stat-label">Tiền thực thu (lũy kế)</div><div class="gg-stat-value">@money($cashIn)</div>
        <small>đã thu − đã hoàn (@money($refunded))</small>
    </div></div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="fa fa-fire text-danger"></i> Top 5 cây bán chạy <span class="small fw-normal text-muted">(số cây đã giao)</span></div>
            <div class="card-body">
                @if($topPlants->isEmpty())
                    <div class="text-center text-muted py-3">Chưa có đơn hoàn thành nào.</div>
                @else
                    <ol class="gg-meters gg-meters-ranked mb-0" data-testid="top-plants">
                        @foreach($topPlants as $row)
                            <li><span class="gg-meter-name" title="{{ $row->plant_name }}">{{ $row->plant_name }}</span>
                                <span class="gg-meter-bar"><span style="width: {{ max(2, round($row->sold * 100 / $topMax)) }}%"></span></span>
                                <span class="gg-meter-value">{{ $row->sold }}</span></li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between">
                <span><i class="fa fa-triangle-exclamation text-warning"></i> Cây dưới ngưỡng tồn</span>
                <a href="{{ route('admin.inventory.index', ['low' => 1]) }}" class="small">Xem kho</a>
            </div>
            <table class="table mb-0">
                <thead><tr><th>Cây</th><th class="text-end">Tồn / Ngưỡng</th></tr></thead>
                <tbody>
                    @forelse($lowStock as $p)
                        <tr><td><a href="{{ route('admin.inventory.show', $p) }}" class="text-decoration-none">{{ $p->name }}</a></td>
                            <td class="text-end"><span class="badge bg-danger">{{ $p->stock }}</span> / {{ $p->reorder_point }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">Kho còn đủ hàng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="fa fa-receipt"></i> Đơn mới nhất</div>
            <div class="table-responsive">
            <table class="table mb-0 align-middle text-nowrap">
                <thead><tr><th>Mã</th><th>Người nhận</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th><th></th></tr></thead>
                <tbody>
                    @foreach($latestOrders as $order)
                        <tr>
                            <td>{{ $order->code }}</td><td>{{ $order->recipient_name }}</td><td>@money($order->total)</td>
                            <td><span class="badge bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span></td>
                            <td><span class="badge bg-{{ $order->payment_status->color() }}">{{ $order->payment_status->label() }}</span></td>
                            <td><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-green">Xem</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Số liệu biểu đồ: JSON trong thẻ riêng (không chèn biến vào JS) --}}
@php $chartData = ['labels' => array_column($trend['days'], 'label'), 'revenue' => array_column($trend['days'], 'revenue'), 'orders' => array_column($trend['days'], 'orders')]; @endphp
<script type="application/json" id="revenue-data">@json($chartData)</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        var wrap = document.getElementById('revenue-chart-wrap');
        if (!window.Chart || !wrap) { wrap && wrap.classList.add('gg-chart-off'); return; }   // CDN lỗi: còn bảng số liệu
        var d = JSON.parse(document.getElementById('revenue-data').textContent);
        var money = function (v) { return new Intl.NumberFormat('vi-VN').format(v) + 'đ'; };
        var compact = function (v) {
            if (v >= 1e9) return (v / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tỷ';
            if (v >= 1e6) return (v / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tr';
            if (v >= 1e3) return Math.round(v / 1e3) + 'k';
            return v;
        };
        new Chart(document.getElementById('revenue-chart'), {
            // Cột theo ngày: doanh thu mỗi ngày là giá trị rời rạc (đường cong sẽ vẽ ra "dốc" giữa các ngày không có đơn)
            type: 'bar',
            data: { labels: d.labels, datasets: [{
                label: 'Doanh thu', data: d.revenue, backgroundColor: '#2e7d32', hoverBackgroundColor: '#1b5e20',
                borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 18,
                categoryPercentage: .85, barPercentage: .9, minBarLength: 0
            }] },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#1f2a23', padding: 10, displayColors: false, callbacks: {
                        title: function (i) { return 'Ngày ' + i[0].label; },
                        label: function (i) { return 'Doanh thu: ' + money(i.parsed.y); },
                        afterLabel: function (i) { return 'Đơn hoàn thành: ' + d.orders[i.dataIndex]; }
                    } }
                },
                scales: {
                    x: { grid: { display: false }, border: { color: '#d7dfd7' }, ticks: { color: '#6b7a70', maxTicksLimit: 8, maxRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, border: { display: false }, grid: { color: '#eef2ee' },
                         ticks: { color: '#6b7a70', maxTicksLimit: 5, callback: compact } }
                }
            }
        });
    })();
</script>
@endpush

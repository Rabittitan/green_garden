@extends('layouts.admin')
@section('title', 'Thống kê')

@section('content')
@php
    use App\Http\Controllers\Admin\DashboardController;
    $cur = $stats['cur'];
    $prev = $stats['prev'];
    // % so với kỳ trước: null khi kỳ trước = 0 (không chia cho 0, không bịa % tăng)
    $delta = fn (int $now, int $before) => $before > 0 ? round(($now - $before) * 100 / $before, 1) : null;
    $cards = [
        ['Doanh thu hàng hóa', $cur['revenue'], $prev['revenue'], true, 'fa-sack-dollar', '#e8f5e9', '#2e7d32', 'sau giảm giá và hoàn tiền, không gồm ship'],
        ['Giá vốn hàng bán', $cur['cogs'], $prev['cogs'], true, 'fa-boxes-stacked', '#fff4d6', '#8a5a00', 'theo giá vốn lúc bán'],
        ['Lợi nhuận gộp', $cur['profit'], $prev['profit'], true, 'fa-chart-line', '#e3f2fd', '#1565c0', 'doanh thu − giá vốn'],
        ['Đơn hoàn thành', $cur['orders'], $prev['orders'], false, 'fa-receipt', '#f3e5f5', '#6a1b9a', 'giao thành công trong kỳ'],
        ['Giá trị TB / đơn', $cur['aov'], $prev['aov'], true, 'fa-scale-balanced', '#e0f2f1', '#00695c', 'doanh thu ÷ số đơn'],
        ['Phí ship thu của khách', $cur['shipping'], $prev['shipping'], true, 'fa-truck', '#eceff1', '#455a64', 'không tính vào doanh thu hàng'],
    ];
    $catMax = max(1, (int) collect($byCategory)->max('revenue'));
    $rate = fn (int $n) => $rates['placed'] > 0 ? number_format($n * 100 / $rates['placed'], 1, ',', '.').'%' : '—';
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <span class="gg-eyebrow">Tổng quan</span>
        <h4 class="fw-bold mb-0">Thống kê kinh doanh</h4>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Chọn kỳ thống kê">
            @foreach(DashboardController::PERIODS as $k => [$label])
                <a href="{{ route('admin.dashboard', ['ky' => $k]) }}" class="btn {{ $period === (string) $k ? 'btn-success' : 'btn-outline-success' }}">{{ $label }}</a>
            @endforeach
        </div>
        <span class="small text-muted">so với {{ $days }} ngày liền trước</span>
    </div>
</div>

{{-- ===== THẺ SỐ LIỆU KỲ NÀY ===== --}}
<div class="row g-3 mb-3" data-testid="kpis">
    @foreach($cards as [$label, $now, $before, $isMoney, $icon, $bg, $fg, $hint])
        @php $d = $delta($now, $before); @endphp
        <div class="col-sm-6 col-xl-4"><div class="gg-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div class="gg-stat-icon" style="background:{{ $bg }};color:{{ $fg }}"><i class="fa {{ $icon }}"></i></div>
                @if($d !== null)
                    <span class="gg-delta {{ $d >= 0 ? 'up' : 'down' }} small"><i class="fa fa-arrow-{{ $d >= 0 ? 'up' : 'down' }}"></i> {{ $d >= 0 ? 'Tăng' : 'Giảm' }} {{ number_format(abs($d), 1, ',', '.') }}%</span>
                @endif
            </div>
            <div class="gg-stat-label">{{ $label }} · {{ $periodLabel }}</div>
            <div class="gg-stat-value">{{ $isMoney ? \App\Support\Format::money($now) : number_format($now, 0, ',', '.') }}</div>
            <small>{{ $hint }} · kỳ trước {{ $isMoney ? \App\Support\Format::money($before) : number_format($before, 0, ',', '.') }}</small>
        </div></div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    {{-- ===== DOANH THU THEO NGÀY ===== --}}
    <div class="col-xl-8">
        <div class="gg-panel gg-panel-body h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="gg-stat-label">Doanh thu hàng hóa theo ngày · {{ $periodLabel }}</div>
                    <div class="gg-hero-value" data-testid="trend-total">@money($trend['total'])</div>
                </div>
                <div class="gg-mini-kpis">
                    <div><small>Tỉ lệ hủy</small><strong>{{ $rate($rates['cancelled']) }}</strong></div>
                    <div><small>Tỉ lệ trả hàng</small><strong>{{ $rate($rates['returned']) }}</strong></div>
                </div>
            </div>
            <div class="gg-chart" id="revenue-chart-wrap">
                <canvas id="revenue-chart" role="img" aria-label="Biểu đồ doanh thu hàng hóa theo ngày, {{ $periodLabel }}"></canvas>
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
            <p class="small text-muted mb-0 mt-2">Tính theo ngày giao thành công (giờ Việt Nam). Tỉ lệ hủy / trả hàng tính trên {{ number_format($rates['placed'], 0, ',', '.') }} đơn đặt trong kỳ.</p>
        </div>
    </div>

    {{-- ===== THEO DANH MỤC ===== --}}
    <div class="col-xl-4">
        <div class="gg-panel gg-panel-body h-100">
            <div class="gg-stat-label mb-2"><i class="fa fa-layer-group"></i> Doanh thu theo danh mục</div>
            @if($byCategory === [])
                <div class="text-muted small py-3">Chưa có đơn hoàn thành trong kỳ.</div>
            @else
                <ul class="gg-meters gg-meters-money" data-testid="by-category">
                    @foreach($byCategory as $c)
                        <li><span class="gg-meter-name" title="{{ $c['name'] }}">{{ $c['name'] }}</span>
                            <span class="gg-meter-bar"><span style="width: {{ max(2, round($c['revenue'] * 100 / $catMax)) }}%"></span></span>
                            <span class="gg-meter-value gg-meter-money">@money($c['revenue'])</span></li>
                    @endforeach
                </ul>
                <p class="small text-muted mb-0 mt-2">Giá trị dòng hàng sau giảm giá (chưa trừ tiền hoàn lẻ của đơn).</p>
            @endif
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- ===== TOP CÂY ===== --}}
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="fa fa-fire text-danger"></i> Top 5 cây theo doanh thu</div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle" data-testid="top-plants">
                    <thead><tr><th>#</th><th>Cây</th><th class="text-end">Đã bán</th><th class="text-end">Doanh thu</th><th class="text-end">Lãi gộp</th></tr></thead>
                    <tbody>
                        @forelse($topPlants as $i => $p)
                            <tr><td>{{ $i + 1 }}</td><td>{{ $p['name'] }}</td><td class="text-end">{{ $p['qty'] }}</td>
                                <td class="text-end fw-bold">@money($p['revenue'])</td>
                                <td class="text-end {{ $p['profit'] < 0 ? 'text-danger' : 'text-success' }}">@money($p['profit'])
                                    <div class="small text-muted">{{ $p['revenue'] > 0 ? number_format($p['profit'] * 100 / $p['revenue'], 1, ',', '.') : 0 }}%</div></td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Chưa có đơn hoàn thành trong kỳ.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{-- ===== TOP KHÁCH ===== --}}
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="fa fa-user-group text-green"></i> Top 5 khách hàng</div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle" data-testid="top-customers">
                    <thead><tr><th>Khách</th><th class="text-end">Đơn</th><th class="text-end">Doanh thu</th></tr></thead>
                    <tbody>
                        @forelse($topCustomers as $c)
                            <tr><td>@if(auth()->user()->isAdmin())<a href="{{ route('admin.customers.show', $c['id']) }}" class="text-decoration-none">{{ $c['name'] }}</a>@else{{ $c['name'] }}@endif
                                    <div class="small text-muted">{{ $c['email'] }}</div></td>
                                <td class="text-end">{{ $c['orders'] }}</td><td class="text-end fw-bold">@money($c['revenue'])</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">Chưa có đơn hoàn thành trong kỳ.</td></tr>
                        @endforelse
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
            // Cột theo ngày: doanh thu mỗi ngày là giá trị rời rạc
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

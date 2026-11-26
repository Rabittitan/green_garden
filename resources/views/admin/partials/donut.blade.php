{{--
    Biểu đồ tròn (vành khuyên) dùng trên trang Thống kê.
    Biến: $id, $items = list<array{label: string, value: int, color: string}>, $money (bool), $centerLabel (string), $empty (string).
    - Màu do controller gán cố định theo đối tượng (DashboardController::PALETTE), không theo hạng; phần mang màu OTHER_COLOR gộp vào "Khác".
    - Chú thích bên cạnh luôn ghi tên + số + %, nên không phải đoán màu (một số màu nhạt trên nền trắng).
    - Chưa tải được Chart.js (CDN lỗi): vẫn hiện vòng tròn vẽ bằng CSS (conic-gradient) + chú thích đầy đủ.
--}}
@php
    $other = \App\Http\Controllers\Admin\DashboardController::OTHER_COLOR;
    $items = array_values(array_filter($items, fn ($i) => $i['value'] > 0));
    $rest = array_values(array_filter($items, fn ($i) => $i['color'] === $other));      // ngoài 6 màu cố định: gộp "Khác"
    if ($rest !== []) {
        $items = [...array_values(array_filter($items, fn ($i) => $i['color'] !== $other)),
            ['label' => count($rest) === 1 ? $rest[0]['label'] : 'Khác ('.count($rest).')', 'value' => array_sum(array_column($rest, 'value')), 'color' => $other]];
    }
    $total = array_sum(array_column($items, 'value'));
    $fmt = fn (int $v) => $money ? \App\Support\Format::money($v) : number_format($v, 0, ',', '.');
    $pct = fn (int $v) => $total > 0 ? number_format($v * 100 / $total, 1, ',', '.').'%' : '0%';
    // Vòng CSS dự phòng: mỗi phần một cung, cách nhau 0,6° màu nền (giống khe 2px của Chart.js)
    $stops = [];
    $at = 0.0;
    foreach ($items as $it) {
        $end = $at + $it['value'] * 360 / max(1, $total);
        $stops[] = $it['color'].' '.round($at, 2).'deg '.round(max($at, $end - (count($items) > 1 ? 0.6 : 0)), 2).'deg';
        $stops[] = '#fff '.round(max($at, $end - (count($items) > 1 ? 0.6 : 0)), 2).'deg '.round($end, 2).'deg';
        $at = $end;
    }
    // @json không nhận mảng nhiều dấu phẩy lồng nhau: dựng sẵn ở đây
    $payload = ['labels' => array_column($items, 'label'), 'values' => array_column($items, 'value'), 'colors' => array_column($items, 'color'), 'money' => $money];
@endphp
@if($total === 0)
    <div class="gg-donut-empty text-muted small" data-testid="{{ $id }}">{{ $empty }}</div>
@else
    <div class="gg-donut" data-testid="{{ $id }}">
        <div class="gg-donut-figure">
            <div class="gg-donut-ring" style="background: conic-gradient({{ implode(', ', $stops) }})" aria-hidden="true"></div>
            <canvas id="{{ $id }}-chart" role="img" aria-label="Biểu đồ tròn: {{ $centerLabel }}"></canvas>
            <div class="gg-donut-center"><strong>{{ $money ? \App\Support\Format::money($total) : number_format($total, 0, ',', '.') }}</strong><small>{{ $centerLabel }}</small></div>
        </div>
        <ul class="gg-donut-legend">
            @foreach($items as $it)
                <li><span class="gg-swatch" style="background: {{ $it['color'] }}"></span>
                    <span class="gg-donut-name" title="{{ $it['label'] }}">{{ $it['label'] }}</span>
                    <span class="gg-donut-value">{{ $fmt($it['value']) }}</span>
                    <span class="gg-donut-pct">{{ $pct($it['value']) }}</span></li>
            @endforeach
        </ul>
        <script type="application/json" data-donut="{{ $id }}-chart">@json($payload)</script>
    </div>
@endif

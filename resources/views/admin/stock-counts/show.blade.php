@extends('layouts.admin')
@section('title', 'Phiếu kiểm kê '.$count->code)

@section('content')
@php
    $draft = $count->status === 'draft';
    $sum = fn (string $key) => collect($preview)->sum(fn ($p) => is_array($p[$key]) ? count($p[$key]) : $p[$key]);
    $missingValue = $count->items->sum(fn ($i) => count($preview[$i->id]['missing'] ?? []) * $i->unit_cost);
    $short = $count->items->filter(fn ($i) => $i->variance() < 0);
    $over = $count->items->filter(fn ($i) => $i->variance() > 0);
@endphp
<a href="{{ route('admin.stock-counts.index') }}" class="text-green text-decoration-none no-print"><i class="fa fa-arrow-left"></i> Phiếu kiểm kê</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-2">
    <h4 class="fw-bold mb-0">Phiếu {{ $count->code }} <span class="badge {{ ['draft' => 'bg-warning text-dark', 'completed' => 'bg-success', 'cancelled' => 'bg-secondary'][$count->status] }} fs-6">{{ $count->statusLabel() }}</span></h4>
    <button type="button" class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()"><i class="fa fa-print"></i> In báo cáo</button>
</div>
<p class="text-muted mb-3">Phạm vi: {{ $count->category->name ?? 'mọi cây đang bán' }} · Lập bởi {{ $count->creator->name }} lúc @vndt($count->created_at)
    @if($count->completer) · Chốt bởi {{ $count->completer->name }} lúc @vndt($count->completed_at) @endif
    @if($count->note) · {{ $count->note }} @endif</p>

@if($draft)
    <div class="gg-panel gg-panel-body mb-3 no-print" data-count-scan data-scan-url="{{ route('admin.stock-counts.scan', $count) }}">
        <p class="small text-muted mb-2">Đi một lượt quanh kho, quét tem <strong>từng chậu</strong> trên kệ (mỗi chậu tính 1 lần). Khi chốt: chậu nằm yên trong kho
            từ lúc mở phiếu mà chưa quét sẽ ghi <strong>thất lạc</strong>; chậu đang thất lạc mà quét thấy sẽ <strong>về kho</strong>.
            Chậu nhập / giao / hoàn về trong lúc đang đếm không bị tính.</p>
        @include('admin.partials.scanner', ['label' => 'Quét tem chậu trên kệ', 'id' => 'count-scan'])
    </div>
    <div class="d-flex flex-wrap gap-3 mb-3" data-testid="count-summary">
        <div class="gg-mini-stat"><small>Đã quét</small><strong data-total-scanned>{{ $sum('scanned') }}</strong> <small class="d-inline">/ {{ $sum('expected') }} chậu cần thấy</small></div>
        <div class="gg-mini-stat"><small>Chưa thấy (sẽ ghi thất lạc)</small><strong class="text-danger"><span data-total-missing>{{ $sum('missing') }}</span> chậu · @money($missingValue)</strong></div>
        <div class="gg-mini-stat"><small>Tìm thấy lại</small><strong class="text-success" data-total-found>{{ $sum('found') }}</strong></div>
    </div>
@else
    <div class="d-flex flex-wrap gap-3 mb-3" data-testid="count-summary">
        <div class="gg-mini-stat"><small>Thiếu so với sổ</small><strong class="text-danger">{{ $short->sum(fn ($i) => -$i->variance()) }} cây · @money(-$short->sum(fn ($i) => $i->varianceValue()))</strong></div>
        <div class="gg-mini-stat"><small>Thừa so với sổ</small><strong class="text-success">{{ $over->sum(fn ($i) => $i->variance()) }} cây · @money($over->sum(fn ($i) => $i->varianceValue()))</strong></div>
        <div class="gg-mini-stat"><small>Chênh lệch giá trị (theo giá vốn)</small><strong>@money($count->items->sum(fn ($i) => $i->varianceValue()))</strong></div>
    </div>
@endif

<form action="{{ route('admin.stock-counts.update', $count) }}" method="POST">@csrf @method('PUT')
<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle gg-table-stack">
    <thead class="table-success"><tr><th>Cây</th><th class="text-end">Tồn trên sổ</th>
        @if($draft)<th class="text-end">Cần thấy</th><th class="text-end">Đã quét</th><th>Chưa thấy</th><th class="text-end">Tìm lại</th>
        @else<th class="text-end">Sau kiểm kê</th><th class="text-end">Chênh lệch</th><th class="text-end">Giá trị</th>@endif
        <th>Ghi chú</th></tr></thead>
    <tbody>
        @foreach($count->items as $item)
            @php $p = $preview[$item->id] ?? null; $v = $item->variance(); @endphp
            <tr data-count-row="{{ $item->id }}" @if($p) data-missing='@json($p['missing'])' @endif
                class="{{ $draft ? '' : ($v < 0 ? 'table-danger' : ($v > 0 ? 'table-success' : '')) }}">
                <td data-label="Cây">{{ $item->plant->name }}<div class="small text-muted">{{ $item->plant->sku }} · giá vốn @money($item->unit_cost)</div></td>
                <td data-label="Tồn trên sổ" class="text-end">{{ $item->system_qty }}</td>
                @if($draft)
                    <td data-label="Cần thấy" class="text-end">{{ $p['expected'] }}</td>
                    <td data-label="Đã quét" class="text-end fw-bold" data-scanned>{{ $p['scanned'] }}</td>
                    <td data-label="Chưa thấy" class="text-danger"><span data-missing-count>{{ count($p['missing']) }}</span>
                        @if($p['missing'] !== [])<details class="small d-inline-block ms-1"><summary>mã</summary>
                            <div class="gg-code-grid mt-1">@foreach(array_slice($p['missing'], 0, 60) as $c)<a href="{{ route('admin.units.index', ['ma' => $c]) }}" data-code="{{ $c }}">{{ \App\Support\UnitCode::format($c) }}</a>@endforeach</div>
                        </details>@endif</td>
                    <td data-label="Tìm lại" class="text-end text-success" data-found>{{ count($p['found']) }}</td>
                @else
                    <td data-label="Sau kiểm kê" class="text-end">{{ $item->counted_qty ?? '—' }}</td>
                    <td data-label="Chênh lệch" class="text-end fw-bold">{{ $v === null ? '—' : ($v > 0 ? '+'.$v : $v) }}</td>
                    <td data-label="Giá trị" class="text-end">{{ $v ? \App\Support\Format::money($item->varianceValue()) : '' }}</td>
                @endif
                <td data-label="Ghi chú">@if($draft)<input name="notes[{{ $item->id }}]" maxlength="255" value="{{ old("notes.{$item->id}", $item->note) }}" class="form-control form-control-sm" placeholder="VD: 2 chậu để kho sau" aria-label="Ghi chú {{ $item->plant->name }}">@else {{ $item->note }} @endif</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
@if($draft)
    <div class="d-flex flex-wrap gap-2 no-print">
        <button class="btn btn-outline-green" name="action" value="save"><i class="fa fa-floppy-disk"></i> Lưu ghi chú</button>
        <button class="btn btn-green" name="action" value="complete" onclick="return confirm('Chốt phiếu? Chậu chưa quét thấy sẽ ghi thất lạc, chậu tìm lại về kho. Không sửa lại được.')"><i class="fa fa-check-double"></i> Chốt phiếu</button>
    </div>
@endif
</form>
@if($draft)
    <form action="{{ route('admin.stock-counts.cancel', $count) }}" method="POST" class="mt-2 no-print" onsubmit="return confirm('Hủy phiếu này? Kho không thay đổi.')">@csrf
        <button class="btn btn-link text-danger p-0">Hủy phiếu</button></form>
@endif
@endsection

@if($draft)
@push('scripts')
<script>
    // Mỗi lần quét: ghi vào phiếu (server kiểm: đúng phạm vi, chậu có thể nằm trên kệ, chưa quét) rồi cập nhật số trên bảng
    (function () {
        var box = document.querySelector('[data-count-scan]'), scanner = document.getElementById('count-scan');
        var token = document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content
            : document.querySelector('input[name=_token]').value;
        function bump(sel, d) { var el = document.querySelector(sel); el.textContent = parseInt(el.textContent, 10) + d; }
        scanner.addEventListener('gg:scan', function (e) {
            if (!e.detail.ok) return;
            var fd = new FormData(); fd.append('code', e.detail.unit.code); fd.append('_token', token);
            fetch(box.dataset.scanUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j.ok) return scanner.ggAccept(false, j.message || 'Không ghi được.');
                    var row = document.querySelector('[data-count-row="' + j.item + '"]');
                    bump('[data-count-row="' + j.item + '"] [data-scanned]', 1); bump('[data-total-scanned]', 1);
                    var missing = JSON.parse(row.dataset.missing || '[]'), at = missing.indexOf(j.code);
                    if (at >= 0) {
                        missing.splice(at, 1); row.dataset.missing = JSON.stringify(missing);
                        bump('[data-count-row="' + j.item + '"] [data-missing-count]', -1); bump('[data-total-missing]', -1);
                        var link = row.querySelector('[data-code="' + j.code + '"]'); if (link) link.remove();
                    }
                    if (j.found) { bump('[data-count-row="' + j.item + '"] [data-found]', 1); bump('[data-total-found]', 1); }
                    scanner.ggAccept(true, (j.found ? 'Tìm thấy lại ' : 'Đã đếm ') + j.display + ' · ' + j.plant_name);
                })
                .catch(function () { scanner.ggAccept(false, 'Mất kết nối, quét lại.'); });
        });
    })();
</script>
@endpush
@endif

@extends('layouts.admin')
@section('title', 'Phiếu kiểm kê '.$count->code)

@section('content')
@php
    $draft = $count->status === 'draft';
    $counted = $count->items->whereNotNull('counted_qty');
    $short = $counted->filter(fn ($i) => $i->variance() < 0);
    $over = $counted->filter(fn ($i) => $i->variance() > 0);
@endphp
<a href="{{ route('admin.stock-counts.index') }}" class="text-green text-decoration-none no-print"><i class="fa fa-arrow-left"></i> Phiếu kiểm kê</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-2">
    <h4 class="fw-bold mb-0">Phiếu {{ $count->code }} <span class="badge {{ ['draft' => 'bg-warning text-dark', 'completed' => 'bg-success', 'cancelled' => 'bg-secondary'][$count->status] }} fs-6">{{ $count->statusLabel() }}</span></h4>
    <button type="button" class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()"><i class="fa fa-print"></i> In báo cáo</button>
</div>
<p class="text-muted mb-3">Phạm vi: {{ $count->category->name ?? 'mọi cây đang bán' }} · Lập bởi {{ $count->creator->name }} lúc @vndt($count->created_at)
    @if($count->completer) · Chốt bởi {{ $count->completer->name }} lúc @vndt($count->completed_at) @endif
    @if($count->note) · {{ $count->note }} @endif</p>

{{-- ===== BÁO CÁO TỔNG ===== --}}
<div class="d-flex flex-wrap gap-3 mb-3" data-testid="count-summary">
    <div class="gg-mini-stat"><small>Đã đếm</small><strong>{{ $counted->count() }} / {{ $count->items->count() }} cây</strong></div>
    <div class="gg-mini-stat"><small>Thiếu so với sổ</small><strong class="text-danger">{{ $short->sum(fn ($i) => -$i->variance()) }} cây · @money(-$short->sum(fn ($i) => $i->varianceValue()))</strong></div>
    <div class="gg-mini-stat"><small>Thừa so với sổ</small><strong class="text-success">{{ $over->sum(fn ($i) => $i->variance()) }} cây · @money($over->sum(fn ($i) => $i->varianceValue()))</strong></div>
    <div class="gg-mini-stat"><small>Chênh lệch giá trị (theo giá vốn)</small><strong>@money($counted->sum(fn ($i) => $i->varianceValue()))</strong></div>
</div>

<form action="{{ route('admin.stock-counts.update', $count) }}" method="POST">@csrf @method('PUT')
<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success"><tr><th>Cây</th><th class="text-end">Tồn trên sổ</th><th class="text-end" style="width:130px">Số đếm</th><th class="text-end">Chênh lệch</th><th class="text-end">Giá trị</th><th>Ghi chú</th></tr></thead>
    <tbody>
        @foreach($count->items as $item)
            @php $v = $item->variance(); @endphp
            <tr class="{{ $v === null ? '' : ($v < 0 ? 'table-danger' : ($v > 0 ? 'table-success' : '')) }}">
                <td>{{ $item->plant->name }}<div class="small text-muted">{{ $item->plant->sku }} · giá vốn @money($item->unit_cost)</div></td>
                <td class="text-end">{{ $item->system_qty }}</td>
                <td class="text-end">
                    @if($draft)
                        <input type="number" min="0" name="lines[{{ $item->id }}][counted_qty]" value="{{ old("lines.{$item->id}.counted_qty", $item->counted_qty) }}" class="form-control form-control-sm text-end" aria-label="Số đếm {{ $item->plant->name }}">
                    @else {{ $item->counted_qty ?? '—' }} @endif
                </td>
                <td class="text-end fw-bold">{{ $v === null ? '—' : ($v > 0 ? '+'.$v : $v) }}</td>
                <td class="text-end">{{ $v ? \App\Support\Format::money($item->varianceValue()) : '' }}</td>
                <td>@if($draft)<input name="lines[{{ $item->id }}][note]" maxlength="255" value="{{ old("lines.{$item->id}.note", $item->note) }}" class="form-control form-control-sm" placeholder="VD: 2 cây héo">@else {{ $item->note }} @endif</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
@if($draft)
    <div class="d-flex flex-wrap gap-2 no-print">
        <button class="btn btn-outline-green" name="action" value="save"><i class="fa fa-floppy-disk"></i> Lưu số đếm</button>
        <button class="btn btn-green" name="action" value="complete" onclick="return confirm('Chốt phiếu? Kho sẽ được điều chỉnh theo chênh lệch và không sửa lại được.')"><i class="fa fa-check-double"></i> Chốt phiếu</button>
    </div>
@endif
</form>
@if($draft)
    <form action="{{ route('admin.stock-counts.cancel', $count) }}" method="POST" class="mt-2 no-print" onsubmit="return confirm('Hủy phiếu này? Kho không thay đổi.')">@csrf
        <button class="btn btn-link text-danger p-0">Hủy phiếu</button></form>
@endif
@endsection

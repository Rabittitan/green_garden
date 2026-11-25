@extends('layouts.admin')
@section('title', 'Flash sale - '.$sale->name)

@section('content')
@use('App\Enums\FlashSaleStatus', 'FS')
@php $me = auth()->user(); @endphp
<a href="{{ route('admin.flash-sales.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Flash sale</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-2">
    <h4 class="fw-bold mb-0">{{ $sale->name }} <span class="badge bg-{{ $sale->status->color() }} fs-6">{{ $sale->phaseLabel() }}</span></h4>
</div>
<p class="text-muted mb-2">@vndt($sale->starts_at) → @vndt($sale->ends_at) · Tạo bởi {{ $sale->creator->name }}
    @if($sale->approver) · Duyệt bởi {{ $sale->approver->name }} lúc @vndt($sale->approved_at) @endif</p>
@if($sale->reject_reason)<div class="alert alert-danger">Lý do từ chối: {{ $sale->reject_reason }}</div>@endif

<div class="d-flex flex-wrap gap-2 mb-3">
    @if($sale->status->isEditable() && ! $me->isAdmin())
        <a href="{{ route('admin.flash-sales.edit', $sale) }}" class="btn btn-outline-success"><i class="fa fa-pen"></i> Sửa</a>
        <form action="{{ route('admin.flash-sales.submit', $sale) }}" method="POST">@csrf<button class="btn btn-green"><i class="fa fa-paper-plane"></i> Gửi duyệt</button></form>
    @endif
    @if($me->isAdmin() && $sale->status === FS::Submitted)
        <form action="{{ route('admin.flash-sales.approve', $sale) }}" method="POST" onsubmit="return confirm('Duyệt đợt sale này?')">@csrf<button class="btn btn-green"><i class="fa fa-check"></i> Duyệt</button></form>
        <form action="{{ route('admin.flash-sales.reject', $sale) }}" method="POST" class="d-flex gap-1">@csrf
            <input name="reason" class="form-control form-control-sm" placeholder="Lý do từ chối" required maxlength="255">
            <button class="btn btn-outline-danger btn-sm text-nowrap">Từ chối</button></form>
    @endif
    @if($sale->status !== FS::Cancelled && ($me->isAdmin() || ($sale->status !== FS::Approved && $sale->created_by === $me->id)))
        <form action="{{ route('admin.flash-sales.cancel', $sale) }}" method="POST" onsubmit="return confirm('Hủy đợt sale này?')">@csrf
            <button class="btn btn-outline-danger"><i class="fa fa-ban"></i> Hủy đợt</button></form>
    @endif
</div>

<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success"><tr><th>Cây</th><th class="text-end">Giá bán</th><th class="text-end">Giá sale</th><th class="text-end">Giảm</th><th class="text-end">Lãi gộp / cây</th><th class="text-end">Đã bán / suất</th></tr></thead>
    <tbody>
        @foreach($sale->items as $it)
            @php $pct = $it->plant->price > 0 ? round(($it->plant->price - $it->sale_price) * 100 / $it->plant->price) : 0; $margin = $it->sale_price - $it->plant->cost_price; @endphp
            <tr>
                <td>{{ $it->plant->name }}</td>
                <td class="text-end text-muted"><s>@money($it->plant->price)</s></td>
                <td class="text-end fw-bold text-danger">@money($it->sale_price)</td>
                <td class="text-end">−{{ $pct }}%</td>
                <td class="text-end {{ $margin < 0 ? 'text-danger fw-bold' : '' }}">@money($margin)</td>
                <td class="text-end">{{ $it->sold_count }} / {{ $it->quantity_limit ?? '∞' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
<p class="small text-muted">Lãi gộp / cây = giá sale − giá vốn hiện tại (chưa trừ ưu đãi hạng, mã giảm giá và phí ship). Số âm: bán lỗ.</p>
@endsection

@extends('layouts.admin')
@section('title', 'Phí vận chuyển')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h4 class="text-green fw-bold mb-0"><i class="fa fa-truck-fast"></i> Phí vận chuyển</h4>
    <a href="{{ route('admin.shipping-zones.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Thêm vùng</a>
</div>
<p class="small text-muted">Phí ship = <strong>phí cơ bản</strong> (gồm N kg đầu) + <strong>phí mỗi kg thêm</strong> × số kg vượt (làm tròn lên).
    Khối lượng = khối lượng đóng gói của từng cây × số lượng (chưa nhập thì tính {{ number_format(config('shop.shipping.default_weight_grams') / 1000, 1, ',', '.') }} kg/cây).
    Tỉnh chưa gán vùng dùng <strong>vùng mặc định</strong>. Đơn đạt ngưỡng miễn ship (chung {{ \App\Support\Format::money(config('shop.shipping.free_min_order')) }} hoặc theo hạng) vẫn miễn phí.
    @if($zones->isEmpty())<br><span class="text-danger">Chưa có vùng nào: đang thu đồng giá {{ \App\Support\Format::money(config('shop.shipping.base_fee')) }}/đơn.</span>@endif</p>

<div class="row g-3">
    @foreach($zones as $z)
        <div class="col-lg-6">
            <div class="gg-panel gg-panel-body h-100">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div><div class="fw-bold fs-5">{{ $z->name }} @if($z->is_default)<span class="badge bg-success">Mặc định</span>@endif</div>
                        <div class="small">@money($z->base_fee) cho {{ number_format($z->base_weight_grams / 1000, 1, ',', '.') }} kg đầu
                            · +@money($z->extra_fee_per_kg) mỗi kg thêm</div>
                        <div class="small text-muted">Ví dụ đơn 5 kg: @money($z->feeFor(5000))</div></div>
                    <a href="{{ route('admin.shipping-zones.edit', $z) }}" class="btn btn-sm btn-outline-success">Sửa</a>
                </div>
                <div class="small mt-2"><strong>{{ $z->provinces->count() }}</strong> tỉnh/thành:
                    {{ $z->provinces->pluck('province')->implode(', ') ?: ($z->is_default ? 'mọi tỉnh chưa gán vùng' : '—') }}</div>
            </div>
        </div>
    @endforeach
</div>
@if($zones->isNotEmpty() && $unassigned)
    <p class="small text-muted mt-3">Tỉnh chưa gán vùng (đang dùng vùng mặc định): {{ implode(', ', $unassigned) }}.</p>
@endif
@endsection

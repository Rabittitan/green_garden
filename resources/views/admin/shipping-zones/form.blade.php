@extends('layouts.admin')
@section('title', $zone->exists ? 'Sửa vùng phí ship' : 'Thêm vùng phí ship')

@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.shipping-zones.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a>
    {{ $zone->exists ? 'Sửa vùng: '.$zone->name : 'Thêm vùng phí ship' }}</h4>
<form action="{{ $zone->exists ? route('admin.shipping-zones.update', $zone) : route('admin.shipping-zones.store') }}" method="POST">@csrf
    @if($zone->exists) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="gg-panel gg-panel-body">
                <div class="mb-2"><label class="form-label fw-semibold" for="name">Tên vùng *</label>
                    <input id="name" name="name" maxlength="100" required value="{{ old('name', $zone->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="VD: Nội thành TP.HCM">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label fw-semibold small" for="base_fee">Phí cơ bản (đ) *</label>
                        <input type="number" id="base_fee" name="base_fee" min="0" required value="{{ old('base_fee', $zone->base_fee) }}" class="form-control @error('base_fee') is-invalid @enderror"></div>
                    <div class="col-6"><label class="form-label fw-semibold small" for="base_weight_kg">Gồm bao nhiêu kg đầu *</label>
                        <input type="number" step="0.1" id="base_weight_kg" name="base_weight_kg" min="0" required value="{{ old('base_weight_kg', $zone->base_weight_grams / 1000) }}" class="form-control @error('base_weight_kg') is-invalid @enderror"></div>
                    <div class="col-6"><label class="form-label fw-semibold small" for="extra_fee_per_kg">Phí mỗi kg thêm (đ) *</label>
                        <input type="number" id="extra_fee_per_kg" name="extra_fee_per_kg" min="0" required value="{{ old('extra_fee_per_kg', $zone->extra_fee_per_kg) }}" class="form-control @error('extra_fee_per_kg') is-invalid @enderror"></div>
                    <div class="col-6"><label class="form-label fw-semibold small" for="sort_order">Thứ tự</label>
                        <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $zone->sort_order ?? 0) }}" class="form-control"></div>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" @checked(old('is_default', $zone->is_default))>
                    <label class="form-check-label" for="is_default">Vùng mặc định (áp cho tỉnh chưa gán vùng nào)</label>
                </div>
                <button class="btn btn-green mt-3"><i class="fa fa-floppy-disk"></i> Lưu</button>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="gg-panel gg-panel-body">
                <div class="fw-semibold mb-2">Tỉnh / thành thuộc vùng này</div>
                <p class="small text-muted mb-2">Chọn tỉnh đang ở vùng khác thì tỉnh đó chuyển sang vùng này.</p>
                <div class="gg-province-grid">
                    @foreach($provinces as $i => $p)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="provinces[]" value="{{ $p }}" id="pv-{{ $i }}" @checked(in_array($p, old('provinces', $mine), true))>
                            <label class="form-check-label" for="pv-{{ $i }}">{{ $p }} @if(isset($taken[$p]))<span class="small text-muted">({{ $taken[$p] }})</span>@endif</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</form>
@if($zone->exists)
    <form action="{{ route('admin.shipping-zones.destroy', $zone) }}" method="POST" class="mt-3" onsubmit="return confirm('Xóa vùng này? Các tỉnh của vùng sẽ dùng vùng mặc định.')">@csrf @method('DELETE')
        <button class="btn btn-link text-danger p-0"><i class="fa fa-trash"></i> Xóa vùng</button></form>
@endif
@endsection

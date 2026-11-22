@extends('layouts.app')
@section('title', 'Sổ địa chỉ')

@section('content')
@include('account._nav')
<div class="gg-page-head"><h1><i class="fa fa-location-dot text-green"></i> Sổ địa chỉ</h1></div>

<div class="row g-4">
    <div class="col-lg-7">
        @forelse($addresses as $a)
            <div class="gg-panel gg-panel-body mb-3">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <strong>{{ $a->recipient_name }}</strong> · {{ $a->phone }}
                        @if($a->is_default) <span class="badge bg-success">Mặc định</span> @endif
                        <div class="text-muted">{{ $a->full_address }}</div>
                    </div>
                    <div class="d-flex gap-1 align-items-start">
                        @unless($a->is_default)
                            <form action="{{ route('addresses.default', $a) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-green">Đặt mặc định</button></form>
                        @endunless
                        <form action="{{ route('addresses.destroy', $a) }}" method="POST" onsubmit="return confirm('Xóa địa chỉ này?')">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-info">Chưa có địa chỉ nào. Địa chỉ dùng khi đặt hàng sẽ được lưu tự động.</div>
        @endforelse
    </div>
    <div class="col-lg-5">
        <form action="{{ route('addresses.store') }}" method="POST" class="gg-panel gg-panel-body gg-sticky">
            @csrf
            <h2 class="h6 fw-bold mb-3"><i class="fa fa-plus text-green"></i> Thêm địa chỉ</h2>
            @foreach(['recipient_name' => 'Họ tên người nhận', 'phone' => 'Số điện thoại'] as $f => $label)
                <div class="mb-2">
                    <input name="{{ $f }}" value="{{ old($f) }}" placeholder="{{ $label }} *" class="form-control @error($f) is-invalid @enderror">
                    @error($f) <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endforeach
            @include('partials.address-select', ['province' => old('province'), 'ward' => old('ward'), 'col' => 'col-12'])
            <div class="mb-2">
                <input name="address_line" value="{{ old('address_line') }}" placeholder="Số nhà, tên đường *" class="form-control @error('address_line') is-invalid @enderror">
                @error('address_line') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="def"><label class="form-check-label" for="def">Đặt làm mặc định</label></div>
            <button class="btn btn-green w-100">Lưu địa chỉ</button>
        </form>
    </div>
</div>
<script src="{{ asset('js/address.js') }}?v={{ @filemtime(public_path('js/address.js')) }}"></script>
@endsection

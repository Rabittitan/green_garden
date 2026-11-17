@extends('layouts.app')
@section('title', 'Sổ địa chỉ')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-location-dot"></i> Sổ địa chỉ</h4>

<div class="row g-4">
    <div class="col-lg-7">
        @forelse($addresses as $a)
            <div class="card card-body shadow-sm mb-3">
                <div class="d-flex justify-content-between">
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
        <form action="{{ route('addresses.store') }}" method="POST" class="card card-body shadow-sm">
            @csrf
            <h6 class="fw-bold">Thêm địa chỉ</h6>
            @foreach(['recipient_name' => 'Họ tên người nhận', 'phone' => 'Số điện thoại', 'province' => 'Tỉnh / Thành phố', 'district' => 'Quận / Huyện', 'ward' => 'Phường / Xã', 'address_line' => 'Số nhà, tên đường'] as $f => $label)
                <div class="mb-2">
                    <input name="{{ $f }}" value="{{ old($f) }}" placeholder="{{ $label }} *" class="form-control @error($f) is-invalid @enderror">
                    @error($f) <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endforeach
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="def"><label class="form-check-label" for="def">Đặt làm mặc định</label></div>
            <button class="btn btn-green">Lưu địa chỉ</button>
        </form>
    </div>
</div>
@endsection

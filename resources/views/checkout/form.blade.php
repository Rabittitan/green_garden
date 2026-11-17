@extends('layouts.app')
@section('title', 'Đặt hàng')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-truck"></i> Thông tin đặt hàng</h4>

<div class="row g-4">
    <div class="col-md-7">
        <form action="{{ route('checkout.store') }}" method="POST" class="card card-body shadow-sm">
            @csrf
            <div class="mb-3">
                <label class="form-label">Họ tên người nhận *</label>
                {{-- old(...) giữ lại dữ liệu khi lỗi; mặc định lấy thông tin tài khoản --}}
                <input type="text" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror"
                       value="{{ old('customer_name', auth()->user()->name) }}">
                @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Số điện thoại *</label>
                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                       value="{{ old('phone', auth()->user()->phone) }}">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Địa chỉ giao hàng *</label>
                <input type="text" name="address" class="form-control @error('address') is-invalid @enderror"
                       value="{{ old('address', auth()->user()->address) }}">
                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Ghi chú</label>
                <textarea name="note" rows="3" class="form-control">{{ old('note') }}</textarea>
            </div>
            <button class="btn btn-green btn-lg">Xác nhận đặt hàng</button>
        </form>
    </div>

    <div class="col-md-5">
        <div class="card card-body shadow-sm">
            <h6 class="fw-bold">Đơn hàng của bạn</h6>
            <ul class="list-group list-group-flush mb-2">
                @foreach($cart as $item)
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>{{ $item['name'] }} <span class="text-muted">x{{ $item['quantity'] }}</span></span>
                        <span>{{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}đ</span>
                    </li>
                @endforeach
            </ul>
            <div class="d-flex justify-content-between fs-5 fw-bold">
                <span>Tổng:</span><span class="text-green">{{ number_format($total, 0, ',', '.') }}đ</span>
            </div>
            <small class="text-muted mt-2">Thanh toán khi nhận hàng (COD).</small>
        </div>
    </div>
</div>
@endsection
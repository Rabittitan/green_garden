@extends('layouts.app')
@section('title', 'Đặt hàng')

@section('content')
@php
    $default = $addresses->firstWhere('is_default', true) ?? $addresses->first();
    $val = fn ($field) => old($field, $default?->{$field} ?? ($field === 'recipient_name' ? $user->name : ($field === 'phone' ? $user->phone : '')));
@endphp
<h4 class="text-green fw-bold mb-3"><i class="fa fa-truck"></i> Xem lại và đặt hàng</h4>

<div class="row g-4">
    {{-- ===== CỘT TRÁI: giao hàng + thanh toán ===== --}}
    <div class="col-lg-7">
        <form id="checkout-form" action="{{ route('checkout.store') }}" method="POST" class="card card-body shadow-sm">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $token }}">
            <input type="hidden" name="expected_total" value="{{ $quote->total() }}">

            <h6 class="fw-bold">1. Địa chỉ nhận hàng</h6>
            @if($addresses->isNotEmpty())
                <select class="form-select mb-3" id="address-picker">
                    <option value="">-- Chọn từ sổ địa chỉ --</option>
                    @foreach($addresses as $a)
                        <option value="{{ $a->id }}" data-address='@json($a->toShippingSnapshot())' @selected($a->id === $default?->id)>
                            {{ $a->recipient_name }} - {{ $a->phone }} - {{ $a->full_address }}{{ $a->is_default ? ' (mặc định)' : '' }}
                        </option>
                    @endforeach
                </select>
            @endif
            <div class="row">
                @foreach([
                    'recipient_name' => ['Họ tên người nhận', 'col-md-6'], 'phone' => ['Số điện thoại', 'col-md-6'],
                    'province' => ['Tỉnh / Thành phố', 'col-md-4'], 'district' => ['Quận / Huyện', 'col-md-4'], 'ward' => ['Phường / Xã', 'col-md-4'],
                    'address_line' => ['Số nhà, tên đường', 'col-12'],
                ] as $field => [$label, $col])
                    <div class="{{ $col }} mb-3">
                        <label class="form-label">{{ $label }} *</label>
                        <input type="text" name="{{ $field }}" value="{{ $val($field) }}" data-field="{{ $field }}"
                               class="form-control @error($field) is-invalid @enderror">
                        @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="save_address" value="1" id="save_address" @checked(old('save_address'))>
                <label class="form-check-label" for="save_address">Lưu địa chỉ này vào sổ địa chỉ</label>
            </div>
            <div class="mb-3">
                <label class="form-label">Ghi chú cho shop</label>
                <textarea name="note" rows="2" class="form-control" maxlength="1000">{{ old('note') }}</textarea>
            </div>

            <h6 class="fw-bold mt-2">2. Phương thức thanh toán</h6>
            @error('payment_method') <div class="text-danger small">{{ $message }}</div> @enderror
            @foreach($methods as $m)
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="payment_method" value="{{ $m->value }}" id="pm-{{ $m->value }}"
                           @checked(old('payment_method', 'cod') === $m->value)>
                    <label class="form-check-label" for="pm-{{ $m->value }}">
                        {{ $m->label() }}
                        @if($m->isPrepaid())
                            <span class="text-muted small">— giữ hàng {{ $m->holdMinutes() >= 60 ? intdiv($m->holdMinutes(), 60).' giờ' : $m->holdMinutes().' phút' }} chờ thanh toán</span>
                        @endif
                    </label>
                </div>
            @endforeach
            @error('checkout_token') <div class="alert alert-danger mt-2">{{ $message }}</div> @enderror

            <button class="btn btn-green btn-lg mt-3" id="submit-btn">Đặt hàng · @money($quote->total())</button>
            <small class="text-muted mt-2">Bấm đặt hàng là bạn đồng ý thanh toán đúng số tiền trên. Nếu giá thay đổi, hệ thống sẽ báo để bạn xem lại.</small>
        </form>
    </div>

    {{-- ===== CỘT PHẢI: báo giá ===== --}}
    <div class="col-lg-5">
        <div class="card card-body shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0">Đơn hàng của bạn</h6>
                <span class="badge bg-success">Hạng {{ $quote->tier->name }}</span>
            </div>
            <ul class="list-group list-group-flush mb-2">
                @foreach($quote->lines as $line)
                    <li class="list-group-item px-0">
                        <div class="d-flex justify-content-between">
                            <span>{{ $line->plant->name }} <span class="text-muted">× {{ $line->quantity }}</span></span>
                            <span>@money($line->subtotal())</span>
                        </div>
                        @if($line->memberDiscount + $line->couponDiscount > 0)
                            <div class="small text-danger text-end">−@money($line->memberDiscount + $line->couponDiscount)</div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <table class="table table-sm mb-2">
                <tr><td>Tạm tính</td><td class="text-end">@money($quote->subtotal())</td></tr>
                @if($quote->memberDiscount() > 0)
                    <tr class="text-danger"><td>Ưu đãi hạng {{ $quote->tier->name }} ({{ $quote->memberDiscountPercent }}%)</td><td class="text-end">−@money($quote->memberDiscount())</td></tr>
                @endif
                @if($quote->couponDiscount() > 0)
                    <tr class="text-danger"><td>Mã {{ $quote->coupon->code }}</td><td class="text-end">−@money($quote->couponDiscount())</td></tr>
                @endif
                <tr><td>Phí vận chuyển</td><td class="text-end">{{ $quote->shippingFee > 0 ? \App\Support\Format::money($quote->shippingFee) : 'Miễn phí' }}</td></tr>
                @if($quote->shippingDiscount > 0)
                    <tr class="text-danger"><td>Mã {{ $quote->coupon->code }} (giảm ship)</td><td class="text-end">−@money($quote->shippingDiscount)</td></tr>
                @endif
                <tr class="fs-5 fw-bold"><td>Tổng thanh toán</td><td class="text-end text-green">@money($quote->total())</td></tr>
            </table>
            @foreach($quote->notices as $notice)
                <div class="alert alert-info small py-2">{{ $notice }}</div>
            @endforeach

            {{-- Mã giảm giá: form riêng (không lồng form) --}}
            @if($quote->coupon)
                <form action="{{ route('checkout.coupon.remove') }}" method="POST" class="d-flex justify-content-between align-items-center">
                    @csrf
                    <span><i class="fa fa-ticket text-success"></i> Đang dùng mã <strong>{{ $quote->coupon->code }}</strong></span>
                    <button class="btn btn-sm btn-outline-danger">Bỏ mã</button>
                </form>
            @else
                <form action="{{ route('checkout.coupon.apply') }}" method="POST" class="input-group">
                    @csrf
                    <input type="text" name="coupon_code" class="form-control" placeholder="Nhập mã giảm giá" value="{{ old('coupon_code') }}" maxlength="40">
                    <button class="btn btn-outline-green">Áp dụng</button>
                </form>
            @endif

            @if($nextTier)
                <div class="small text-muted mt-3"><i class="fa fa-crown text-warning"></i>
                    Mua thêm @money(max(0, $nextTier->min_spend - $user->lifetime_spend)) để lên hạng {{ $nextTier->name }} (giảm {{ $nextTier->discount_percent }}%).</div>
            @endif
        </div>
    </div>
</div>

<script>
    // Chọn địa chỉ trong sổ -> điền vào form
    document.getElementById('address-picker')?.addEventListener('change', function () {
        const data = this.selectedOptions[0]?.dataset.address;
        if (!data) return;
        const a = JSON.parse(data);
        document.querySelectorAll('[data-field]').forEach(el => { if (a[el.dataset.field] !== undefined) el.value = a[el.dataset.field]; });
    });
    // Chặn bấm 2 lần (server vẫn chống trùng bằng checkout_token)
    document.getElementById('checkout-form').addEventListener('submit', function () {
        const b = document.getElementById('submit-btn'); b.disabled = true; b.innerText = 'Đang đặt hàng...';
    });
</script>
@endsection

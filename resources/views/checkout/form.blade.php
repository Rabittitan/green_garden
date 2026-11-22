@extends('layouts.app')
@section('title', 'Đặt hàng')

@section('content')
@php
    $default = $addresses->firstWhere('is_default', true) ?? $addresses->first();
    $val = fn ($field) => old($field, $default?->{$field} ?? ($field === 'recipient_name' ? $user->name : ($field === 'phone' ? $user->phone : '')));
@endphp
<div class="gg-page-head">
    <span class="gg-eyebrow">Thanh toán</span>
    <h1><i class="fa fa-truck text-green"></i> Xem lại và đặt hàng</h1>
</div>

<div class="row g-4">
    {{-- ===== CỘT TRÁI: giao hàng + thanh toán ===== --}}
    <div class="col-lg-7">
        <form id="checkout-form" action="{{ route('checkout.store') }}" method="POST" class="gg-panel gg-panel-body">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $token }}">
            <input type="hidden" name="expected_total" value="{{ $quote->total() }}">

            <div class="gg-step-title"><span class="gg-step-num">1</span> Địa chỉ nhận hàng</div>
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
                @foreach(['recipient_name' => ['Họ tên người nhận', 'col-md-6'], 'phone' => ['Số điện thoại', 'col-md-6']] as $field => [$label, $col])
                    <div class="{{ $col }} mb-3">
                        <label class="form-label fw-semibold" for="f-{{ $field }}">{{ $label }} <span class="text-danger">*</span></label>
                        <input type="text" id="f-{{ $field }}" name="{{ $field }}" value="{{ $val($field) }}" data-field="{{ $field }}"
                               class="form-control @error($field) is-invalid @enderror">
                        @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
            {{-- Tỉnh/thành → Phường/xã: chọn từ danh sách (địa giới từ 01/07/2025) --}}
            @include('partials.address-select', ['province' => $val('province'), 'ward' => $val('ward')])
            <div class="row">
                @foreach(['address_line' => ['Số nhà, tên đường', 'col-12']] as $field => [$label, $col])
                    <div class="{{ $col }} mb-3">
                        <label class="form-label fw-semibold" for="f-{{ $field }}">{{ $label }} <span class="text-danger">*</span></label>
                        <input type="text" id="f-{{ $field }}" name="{{ $field }}" value="{{ $val($field) }}" data-field="{{ $field }}"
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
                <label class="form-label fw-semibold" for="f-note">Ghi chú cho shop</label>
                <textarea id="f-note" name="note" rows="2" class="form-control" maxlength="1000">{{ old('note') }}</textarea>
            </div>

            <div class="gg-step-title mt-2"><span class="gg-step-num">2</span> Phương thức thanh toán</div>
            @error('payment_method') <div class="text-danger small">{{ $message }}</div> @enderror
            @foreach($methods as $m)
                <div class="gg-pay-option">
                    <input class="form-check-input" type="radio" name="payment_method" value="{{ $m->value }}" id="pm-{{ $m->value }}"
                           @checked(old('payment_method', 'cod') === $m->value)>
                    <label for="pm-{{ $m->value }}">
                        <strong>{{ $m->label() }}</strong>
                        @if($m->isPrepaid())
                            <span class="d-block text-muted small">Giữ hàng {{ $m->holdMinutes() >= 60 ? intdiv($m->holdMinutes(), 60).' giờ' : $m->holdMinutes().' phút' }} chờ thanh toán</span>
                        @endif
                    </label>
                </div>
            @endforeach
            @error('checkout_token') <div class="alert alert-danger mt-2">{{ $message }}</div> @enderror

            <button class="btn btn-green btn-lg w-100 mt-3" id="submit-btn">Đặt hàng · @money($quote->total())</button>
            <small class="d-block text-muted mt-2">Bấm đặt hàng là bạn đồng ý thanh toán đúng số tiền trên. Nếu giá thay đổi, hệ thống sẽ báo để bạn xem lại.</small>
        </form>
    </div>

    {{-- ===== CỘT PHẢI: báo giá ===== --}}
    <div class="col-lg-5">
        <div class="gg-panel gg-panel-body gg-sticky">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Đơn hàng của bạn</h2>
                <span class="badge gg-tier"><i class="fa fa-crown"></i> Hạng {{ $quote->tier->name }}</span>
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
                <tr class="fs-5 fw-bold"><td class="border-0 pt-3">Tổng thanh toán</td><td class="text-end text-green border-0 pt-3">@money($quote->total())</td></tr>
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

<script src="{{ asset('js/address.js') }}?v={{ @filemtime(public_path('js/address.js')) }}"></script>
<script>
    // Chọn địa chỉ trong sổ -> điền vào form (tỉnh/xã qua ô chọn: tự tải lại danh sách xã, báo nếu là địa giới cũ)
    document.getElementById('address-picker')?.addEventListener('change', function () {
        const data = this.selectedOptions[0]?.dataset.address;
        if (!data) return;
        const a = JSON.parse(data);
        document.querySelectorAll('input[data-field]').forEach(el => { if (a[el.dataset.field] !== undefined) el.value = a[el.dataset.field]; });
        document.querySelector('[data-gg-address]')?.ggSet?.(a);
    });
    // Chặn bấm 2 lần (server vẫn chống trùng bằng checkout_token)
    document.getElementById('checkout-form').addEventListener('submit', function () {
        const b = document.getElementById('submit-btn'); b.disabled = true; b.innerText = 'Đang đặt hàng...';
    });
</script>
@endsection

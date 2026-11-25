{{-- Ô nhập mẫu mã được tặng (dùng chung cho luật tặng và tặng tay). Biến: $g (CouponGiftRule hoặc null) --}}
@php $g = $g ?? null; @endphp
<div class="mb-2">
    <label class="form-label small fw-semibold" for="g-name">Tên mã (khách thấy) *</label>
    <input id="g-name" name="name" maxlength="150" required value="{{ old('name', $g?->name) }}" class="form-control form-control-sm @error('name') is-invalid @enderror" placeholder="VD: Quà cảm ơn đơn từ 1 triệu">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="row g-2">
    <div class="col-6">
        <label class="form-label small fw-semibold" for="g-type">Loại *</label>
        <select id="g-type" name="type" class="form-select form-select-sm">
            @foreach(['fixed' => 'Giảm số tiền', 'percent' => 'Giảm %', 'free_shipping' => 'Miễn phí ship'] as $k => $l)
                <option value="{{ $k }}" @selected(old('type', $g?->type?->value ?? 'fixed') === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6">
        <label class="form-label small fw-semibold" for="g-value">Giá trị (đ hoặc %)</label>
        <input type="number" id="g-value" name="value" min="0" value="{{ old('value', $g?->value) }}" class="form-control form-control-sm @error('value') is-invalid @enderror">
        @error('value') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-6">
        <label class="form-label small fw-semibold" for="g-max">Giảm tối đa (với %)</label>
        <input type="number" id="g-max" name="max_discount_amount" min="1" value="{{ old('max_discount_amount', $g?->max_discount_amount) }}" class="form-control form-control-sm">
    </div>
    <div class="col-6">
        <label class="form-label small fw-semibold" for="g-min">Dùng cho đơn từ (đ)</label>
        <input type="number" id="g-min" name="min_order_amount" min="0" value="{{ old('min_order_amount', $g?->min_order_amount ?? 0) }}" class="form-control form-control-sm">
    </div>
    <div class="col-6">
        <label class="form-label small fw-semibold" for="g-days">Hạn dùng (ngày) *</label>
        <input type="number" id="g-days" name="valid_days" min="1" max="365" required value="{{ old('valid_days', $g?->valid_days ?? 30) }}" class="form-control form-control-sm @error('valid_days') is-invalid @enderror">
        @error('valid_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

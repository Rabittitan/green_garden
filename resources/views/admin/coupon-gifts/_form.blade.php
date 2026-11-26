<div class="gg-panel gg-panel-body" style="max-width: 640px">
    <div class="mb-3">
        <label class="form-label fw-semibold" for="min_order_total">Tặng khi đơn hoàn thành có tổng tiền từ (đ) *</label>
        <input type="number" id="min_order_total" name="min_order_total" min="0" required value="{{ old('min_order_total', $rule->min_order_total) }}" class="form-control @error('min_order_total') is-invalid @enderror">
        @error('min_order_total') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="fw-semibold mb-2">Mã được tặng</div>
    @include('admin.coupon-gifts._fields', ['g' => $rule])
    <div class="form-check mt-3">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $rule->is_active))>
        <label class="form-check-label" for="is_active">Đang bật</label>
    </div>
    <button class="btn btn-green mt-3"><i class="fa fa-floppy-disk"></i> Lưu</button>
    <a href="{{ route('admin.coupon-gifts.index') }}" class="btn btn-outline-secondary mt-3">Hủy</a>
</div>

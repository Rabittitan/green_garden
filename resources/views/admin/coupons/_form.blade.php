@php $locked = $coupon->exists && $coupon->isLocked(); $sel = fn ($rel) => old($rel.'_ids', $coupon->exists ? $coupon->{$rel === 'plant' ? 'plants' : 'categories'}->pluck('id')->all() : []); @endphp
@if($locked)
    <div class="alert alert-info">Mã đã có {{ $coupon->used_count }} lượt dùng: chỉ đổi được tên, mô tả, thời hạn, số lượt và bật/tắt.</div>
@endif
<div class="row">
    <div class="col-md-3 mb-3"><label class="form-label">Mã *</label>
        <input name="code" value="{{ old('code', $coupon->code) }}" class="form-control text-uppercase" @readonly($locked) required></div>
    <div class="col-md-9 mb-3"><label class="form-label">Tên *</label>
        <input name="name" value="{{ old('name', $coupon->name) }}" class="form-control" required></div>
    <div class="col-12 mb-3"><label class="form-label">Mô tả</label>
        <textarea name="description" rows="2" class="form-control">{{ old('description', $coupon->description) }}</textarea></div>

    <div class="col-md-3 mb-3"><label class="form-label">Loại *</label>
        <select name="type" class="form-select" @disabled($locked)>
            @foreach(\App\Enums\CouponType::cases() as $t)
                <option value="{{ $t->value }}" @selected(old('type', $coupon->type?->value) === $t->value)>{{ ['percent' => 'Giảm %', 'fixed' => 'Giảm số tiền', 'free_shipping' => 'Miễn phí ship'][$t->value] }}</option>
            @endforeach
        </select>
        @if($locked)<input type="hidden" name="type" value="{{ $coupon->type->value }}">@endif</div>
    <div class="col-md-3 mb-3"><label class="form-label">Giá trị (% hoặc đ)</label>
        <input type="number" name="value" min="0" value="{{ old('value', $coupon->value ?? 0) }}" class="form-control" @readonly($locked)></div>
    <div class="col-md-3 mb-3"><label class="form-label">Giảm tối đa (đ)</label>
        <input type="number" name="max_discount_amount" min="1" value="{{ old('max_discount_amount', $coupon->max_discount_amount) }}" class="form-control" @readonly($locked)></div>
    <div class="col-md-3 mb-3"><label class="form-label">Đơn tối thiểu (đ) *</label>
        <input type="number" name="min_order_amount" min="0" value="{{ old('min_order_amount', $coupon->min_order_amount ?? 0) }}" class="form-control" @readonly($locked)></div>

    <div class="col-md-3 mb-3"><label class="form-label">Phạm vi *</label>
        <select name="scope" class="form-select" @disabled($locked)>
            @foreach(['order' => 'Cả đơn', 'plants' => 'Theo cây', 'categories' => 'Theo danh mục'] as $v => $l)
                <option value="{{ $v }}" @selected(old('scope', $coupon->scope?->value) === $v)>{{ $l }}</option>
            @endforeach
        </select>
        @if($locked)<input type="hidden" name="scope" value="{{ $coupon->scope->value }}">@endif</div>
    <div class="col-md-5 mb-3"><label class="form-label">Cây áp dụng (phạm vi theo cây)</label>
        <select name="plant_ids[]" multiple size="4" class="form-select" @disabled($locked)>
            @foreach($plants as $p)<option value="{{ $p->id }}" @selected(in_array($p->id, $sel('plant')))>{{ $p->name }} ({{ $p->sku }})</option>@endforeach
        </select></div>
    <div class="col-md-4 mb-3"><label class="form-label">Danh mục (gồm cả danh mục con)</label>
        <select name="category_ids[]" multiple size="4" class="form-select" @disabled($locked)>
            @foreach($categories as $c)<option value="{{ $c->id }}" @selected(in_array($c->id, $sel('category')))>{{ $c->name }}</option>@endforeach
        </select></div>

    <div class="col-md-3 mb-3"><label class="form-label">Hạng tối thiểu</label>
        <select name="min_tier_id" class="form-select" @disabled($locked)>
            <option value="">Mọi hạng</option>
            @foreach($tiers as $t)<option value="{{ $t->id }}" @selected((int) old('min_tier_id', $coupon->min_tier_id) === $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        @if($locked && $coupon->min_tier_id)<input type="hidden" name="min_tier_id" value="{{ $coupon->min_tier_id }}">@endif</div>
    <div class="col-md-3 mb-3"><label class="form-label">Tổng lượt (trống = không giới hạn)</label>
        <input type="number" name="usage_limit" min="1" value="{{ old('usage_limit', $coupon->usage_limit) }}" class="form-control"></div>
    <div class="col-md-3 mb-3"><label class="form-label">Lượt / khách *</label>
        <input type="number" name="usage_limit_per_user" min="1" value="{{ old('usage_limit_per_user', $coupon->usage_limit_per_user ?? 1) }}" class="form-control"></div>
    <div class="col-md-3 mb-3"></div>
    <div class="col-md-3 mb-3"><label class="form-label">Bắt đầu</label>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\TH:i')) }}" class="form-control"></div>
    <div class="col-md-3 mb-3"><label class="form-label">Kết thúc</label>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $coupon->ends_at?->format('Y-m-d\TH:i')) }}" class="form-control"></div>
</div>
@foreach(['combinable_with_tier' => 'Cộng dồn với ưu đãi hạng (bỏ chọn: hệ thống chọn ưu đãi lợi hơn cho khách)', 'first_order_only' => 'Chỉ cho đơn đầu tiên', 'is_active' => 'Đang bật'] as $f => $label)
    <div class="form-check">
        <input type="hidden" name="{{ $f }}" value="{{ $locked && $f !== 'is_active' ? (int) $coupon->{$f} : 0 }}">
        <input class="form-check-input" type="checkbox" name="{{ $f }}" value="1" id="{{ $f }}" @checked(old($f, $coupon->{$f})) @disabled($locked && $f !== 'is_active')>
        <label class="form-check-label" for="{{ $f }}">{{ $label }}</label>
    </div>
@endforeach
<button class="btn btn-green mt-3"><i class="fa fa-floppy-disk"></i> Lưu</button>
<a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary mt-3">Hủy</a>

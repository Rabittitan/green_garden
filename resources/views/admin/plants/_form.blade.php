{{-- Nhận $categories và $plant (có khi sửa, không có khi thêm). Dùng ?? để tránh lỗi khi $plant không tồn tại --}}
@php $plant = $plant ?? null; @endphp

<div class="row">
    <div class="col-md-8 mb-3">
        <label class="form-label">Tên cây *</label>
        <input type="text" name="name" value="{{ old('name', $plant->name ?? '') }}" class="form-control @error('name') is-invalid @enderror">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Danh mục *</label>
        <select name="category_id" class="form-select @error('category_id') is-invalid @enderror">
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id', $plant->category_id ?? '') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Giá (VNĐ) *</label>
        <input type="number" name="price" value="{{ old('price', $plant->price ?? '') }}" class="form-control @error('price') is-invalid @enderror">
        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Mã SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $plant->sku ?? '') }}" class="form-control @error('sku') is-invalid @enderror" placeholder="Để trống: tự sinh">
        @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Ánh sáng *</label>
        <select name="light" class="form-select">
            @foreach(\App\Models\Plant::LIGHTS as $key => $label)
                <option value="{{ $key }}" @selected(old('light', $plant->light ?? '') == $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Mệnh phong thủy *</label>
        <select name="element" class="form-select">
            @foreach(\App\Models\Plant::ELEMENTS as $key => $label)
                <option value="{{ $key }}" @selected(old('element', $plant->element ?? '') == $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label" for="reorder_point">Ngưỡng nhập thêm *</label>
        {{-- Không điền sẵn: mỗi cây một ngưỡng, người nhập tự quyết (tồn ≤ ngưỡng thì báo "sắp hết", gợi ý nhập thêm) --}}
        <input type="number" id="reorder_point" name="reorder_point" min="0" required placeholder="VD: 10" value="{{ old('reorder_point', $plant->reorder_point ?? '') }}" class="form-control @error('reorder_point') is-invalid @enderror">
        @error('reorder_point') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @if($plant)
        <div class="col-md-5 mb-3">
            <label class="form-label">Tồn kho</label>
            <div class="form-control-plaintext"><strong>{{ $plant->stock }}</strong> cây · <a href="{{ route('admin.inventory.show', $plant) }}">Sổ kho</a></div>
        </div>
        {{-- Giá bán / giá vốn / lãi gộp: giá vốn KHÔNG sửa tay ở đây, nó tự tính từ các phiếu nhập kho --}}
        @php
            $margin = $plant->price - $plant->cost_price;
            $marginPct = $plant->price > 0 ? round($margin * 100 / $plant->price, 1) : 0;
        @endphp
        <div class="col-12 mb-3">
            <div class="gg-panel gg-panel-body">
                <div class="fw-semibold mb-2">Giá bán, giá vốn và lãi gộp</div>
                <div class="d-flex flex-wrap gap-3">
                    <div class="gg-mini-stat"><small>Giá bán (đang nhập ở trên)</small><strong>@money($plant->price)</strong></div>
                    <div class="gg-mini-stat"><small>Giá vốn bình quân</small><strong>@money($plant->cost_price)</strong></div>
                    <div class="gg-mini-stat"><small>Lãi gộp mỗi cây</small><strong class="{{ $margin < 0 ? 'text-danger' : 'text-success' }}">@money($margin) ({{ number_format($marginPct, 1, ',', '.') }}%)</strong></div>
                </div>
                <p class="small text-muted mt-2 mb-2">
                    <strong>Giá vốn bình quân</strong> tự tính khi nhập kho: (tồn cũ × giá vốn cũ + số nhập × giá nhập) ÷ tồn mới.
                    Mỗi đơn bán lưu lại giá vốn tại thời điểm bán để tính lợi nhuận. Lãi gộp chưa trừ phí vận chuyển và các giảm giá của đơn.
                </p>
                @if(! empty($recentReceipts) && $recentReceipts->isNotEmpty())
                    <div class="small fw-semibold">Giá nhập 5 lần gần nhất</div>
                    <ul class="small mb-0">
                        @foreach($recentReceipts as $r)
                            <li>@vndt($r->receipt->received_at) · {{ $r->receipt->supplier->name ?? '' }} · {{ $r->quantity_received }} cây × <strong>@money($r->unit_cost)</strong>
                                (phiếu <a href="{{ route('admin.goods-receipts.show', $r->goods_receipt_id) }}">{{ $r->receipt->code }}</a>)</li>
                        @endforeach
                    </ul>
                @else
                    <div class="small text-muted">Chưa có phiếu nhập kho nào cho cây này (giá vốn đang là giá lúc khai báo tồn đầu kỳ).</div>
                @endif
            </div>
        </div>
    @else
        <div class="col-md-3 mb-3">
            <label class="form-label">Tồn đầu kỳ *</label>
            <input type="number" name="opening_stock" min="0" value="{{ old('opening_stock', 0) }}" class="form-control @error('opening_stock') is-invalid @enderror">
            @error('opening_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label" title="Giá nhập của số cây tồn đầu kỳ; các lần nhập kho sau sẽ tự tính giá vốn bình quân">Giá nhập tồn đầu kỳ / cây *</label>
            <input type="number" name="cost_price" min="0" value="{{ old('cost_price', 0) }}" class="form-control @error('cost_price') is-invalid @enderror">
            @error('cost_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endif
    <div class="col-md-3 mb-3 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $plant->is_active ?? true))>
            <label class="form-check-label" for="is_active">Đang bán</label>
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Link ảnh (URL)</label>
    <input type="text" name="image" value="{{ old('image', $plant->image ?? '') }}" class="form-control @error('image') is-invalid @enderror" placeholder="https://...">
    @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Mô tả</label>
    <textarea name="description" rows="4" class="form-control">{{ old('description', $plant->description ?? '') }}</textarea>
</div>

{{-- Thông tin chi tiết (K8): đều không bắt buộc; trống mục nào thì trang cây ẩn mục đó --}}
<fieldset class="border rounded-3 p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-6 fw-bold mb-0">Thông tin chi tiết <span class="fw-normal text-muted small">(hiện ở trang cây, không bắt buộc)</span></legend>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label" for="height">Kích thước cây</label>
            <input id="height" name="height" maxlength="60" value="{{ old('height', $plant->height ?? '') }}" class="form-control @error('height') is-invalid @enderror" placeholder="Cao 40–50 cm">
            @error('height') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label" for="pot_size">Chậu</label>
            <input id="pot_size" name="pot_size" maxlength="80" value="{{ old('pot_size', $plant->pot_size ?? '') }}" class="form-control @error('pot_size') is-invalid @enderror" placeholder="Chậu sứ Ø15 cm">
            @error('pot_size') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label" for="growth_rate">Tốc độ lớn</label>
            <select id="growth_rate" name="growth_rate" class="form-select @error('growth_rate') is-invalid @enderror">
                <option value="">-- Chưa nhập --</option>
                @foreach(\App\Models\Plant::GROWTH_RATES as $k => $label)
                    <option value="{{ $k }}" @selected(old('growth_rate', $plant->growth_rate ?? '') === $k)>{{ $label }}</option>
                @endforeach
            </select>
            @error('growth_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label" for="watering">Tưới nước</label>
            <input id="watering" name="watering" maxlength="100" value="{{ old('watering', $plant->watering ?? '') }}" class="form-control @error('watering') is-invalid @enderror" placeholder="2–3 lần/tuần">
            @error('watering') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label" for="growth_info">Quá trình sinh trưởng</label>
            <textarea id="growth_info" name="growth_info" rows="5" maxlength="5000" class="form-control @error('growth_info') is-invalid @enderror" placeholder="Cây lớn ra sao theo thời gian, mùa ra lá/ra hoa, khi nào thay chậu...">{{ old('growth_info', $plant->growth_info ?? '') }}</textarea>
            @error('growth_info') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label" for="care_guide">Cách chăm sóc</label>
            <textarea id="care_guide" name="care_guide" rows="5" maxlength="5000" class="form-control @error('care_guide') is-invalid @enderror" placeholder="Mỗi dòng một ý: ánh sáng, đất, phân bón, sâu bệnh...">{{ old('care_guide', $plant->care_guide ?? '') }}</textarea>
            @error('care_guide') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

<button class="btn btn-green"><i class="fa fa-floppy-disk"></i> Lưu</button>
<a href="{{ route('admin.plants.index') }}" class="btn btn-outline-secondary">Hủy</a>
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
        <label class="form-label">Ngưỡng nhập thêm *</label>
        <input type="number" name="reorder_point" min="0" value="{{ old('reorder_point', $plant->reorder_point ?? 5) }}" class="form-control @error('reorder_point') is-invalid @enderror">
        @error('reorder_point') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @if($plant)
        <div class="col-md-5 mb-3">
            <label class="form-label">Tồn kho / Giá vốn</label>
            <div class="form-control-plaintext"><strong>{{ $plant->stock }}</strong> cây · giá vốn @money($plant->cost_price)
                · <a href="{{ route('admin.inventory.show', $plant) }}">Kiểm kê / sổ kho</a></div>
        </div>
    @else
        <div class="col-md-3 mb-3">
            <label class="form-label">Tồn đầu kỳ *</label>
            <input type="number" name="opening_stock" min="0" value="{{ old('opening_stock', 0) }}" class="form-control @error('opening_stock') is-invalid @enderror">
            @error('opening_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Giá vốn / cây *</label>
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

<button class="btn btn-green"><i class="fa fa-floppy-disk"></i> Lưu</button>
<a href="{{ route('admin.plants.index') }}" class="btn btn-outline-secondary">Hủy</a>
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
        <label class="form-label">Tồn kho *</label>
        <input type="number" name="stock" value="{{ old('stock', $plant->stock ?? 0) }}" class="form-control @error('stock') is-invalid @enderror">
        @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
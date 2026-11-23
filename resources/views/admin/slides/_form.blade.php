{{-- Form slide dùng chung tạo / sửa. Biến: $slide --}}
<div class="row g-4">
    <div class="col-lg-7">
        <div class="gg-panel gg-panel-body">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="title">Tiêu đề trên ảnh</label>
                <input id="title" name="title" maxlength="120" value="{{ old('title', $slide->title) }}" class="form-control @error('title') is-invalid @enderror" placeholder="VD: Cây phong thủy đón Tết">
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="subtitle">Dòng phụ</label>
                <input id="subtitle" name="subtitle" maxlength="200" value="{{ old('subtitle', $slide->subtitle) }}" class="form-control @error('subtitle') is-invalid @enderror" placeholder="VD: Giảm 10% cho đơn từ 500.000đ">
                @error('subtitle') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label fw-semibold" for="link_url">Link khi bấm</label>
                    <input id="link_url" name="link_url" maxlength="255" value="{{ old('link_url', $slide->link_url) }}" class="form-control @error('link_url') is-invalid @enderror" placeholder="/cay-canh?element=kim">
                    @error('link_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Đường dẫn trong shop (bắt đầu bằng /) hoặc https://… Để trống = slide không bấm được.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold" for="button_label">Chữ trên nút</label>
                    <input id="button_label" name="button_label" maxlength="40" value="{{ old('button_label', $slide->button_label) }}" class="form-control @error('button_label') is-invalid @enderror" placeholder="Xem ngay">
                    @error('button_label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold" for="sort_order">Thứ tự <span class="text-danger">*</span></label>
                    <input type="number" id="sort_order" name="sort_order" min="0" max="999" required value="{{ old('sort_order', $slide->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror">
                    @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-8 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $slide->is_active))>
                        <label class="form-check-label" for="is_active">Bật (hiện trên trang chủ)</label>
                    </div>
                </div>
            </div>
            <button class="btn btn-green"><i class="fa fa-floppy-disk"></i> Lưu slide</button>
            <a href="{{ route('admin.slides.index') }}" class="btn btn-outline-secondary">Hủy</a>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="gg-panel gg-panel-body">
            <div class="fw-semibold mb-2">Ảnh slide @if(! $slide->exists)<span class="text-danger">*</span>@endif</div>
            @if($slide->image)
                <div class="gg-slide-thumb rounded-3 mb-2"><img src="{{ $slide->image }}" alt="Ảnh hiện tại"></div>
            @endif
            <label class="form-label small mb-1" for="image">{{ $slide->exists ? 'Đổi ảnh: tải ảnh mới' : 'Tải ảnh lên' }} (JPG, PNG, WEBP, GIF · tối đa 4MB · khuyên dùng 1600×600)</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control @error('image') is-invalid @enderror">
            @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <label class="form-label small mb-1 mt-2" for="image_url">hoặc dán link ảnh (https://…)</label>
            <input id="image_url" name="image_url" value="{{ old('image_url') }}" placeholder="https://..." class="form-control form-control-sm @error('image_url') is-invalid @enderror">
            @error('image_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

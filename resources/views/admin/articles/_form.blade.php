{{-- Form bài viết dùng chung cho tạo / sửa. Biến: $article, $plants --}}
@php
    $tz = config('shop.display_timezone');
    $publishedAt = old('published_at', $article->published_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i'));
    $selectedPlants = array_map('intval', old('plant_ids', $article->exists ? $article->plants->pluck('id')->all() : []));
    $status = old('status', $article->status ?? 'draft');
@endphp
<div class="row g-4">
    <div class="col-xl-8">
        <div class="gg-panel gg-panel-body">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="title">Tiêu đề <span class="text-danger">*</span></label>
                <input id="title" name="title" maxlength="255" required value="{{ old('title', $article->title) }}" class="form-control form-control-lg @error('title') is-invalid @enderror">
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="summary">Tóm tắt <span class="text-danger">*</span> <span class="fw-normal text-muted small">(hiện ở thẻ bài, tối đa 300 ký tự)</span></label>
                <textarea id="summary" name="summary" rows="2" maxlength="300" required class="form-control @error('summary') is-invalid @enderror">{{ old('summary', $article->summary) }}</textarea>
                @error('summary') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="form-label fw-semibold" for="content">Nội dung <span class="text-danger">*</span></label>
                <div class="gg-editor-bar" data-editor-for="content" data-upload-url="{{ route('admin.articles.images') }}" data-preview-url="{{ route('admin.articles.preview') }}">
                    <button type="button" class="btn btn-sm btn-light" data-md="bold" title="Chữ đậm"><i class="fa fa-bold"></i></button>
                    <button type="button" class="btn btn-sm btn-light" data-md="heading" title="Tiêu đề mục"><i class="fa fa-heading"></i></button>
                    <button type="button" class="btn btn-sm btn-light" data-md="list" title="Danh sách"><i class="fa fa-list-ul"></i></button>
                    <button type="button" class="btn btn-sm btn-light" data-md="link" title="Chèn link"><i class="fa fa-link"></i></button>
                    <label class="btn btn-sm btn-green mb-0" title="Tải ảnh lên và chèn vào chỗ con trỏ">
                        <i class="fa fa-image"></i> Chèn ảnh
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-md-image hidden>
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-success ms-auto" data-md-preview aria-pressed="false"><i class="fa fa-eye"></i> <span>Xem trước</span></button>
                    <span class="gg-editor-status small text-muted" data-editor-status></span>
                </div>
                <textarea id="content" name="content" rows="18" maxlength="20000" required class="form-control gg-editor @error('content') is-invalid @enderror">{{ old('content', $article->content) }}</textarea>
                {{-- Xem trước: nội dung đang gõ hiện đúng như trang bài (ảnh hiện thật, không phải dòng ![...](...)) --}}
                <div class="gg-editor-preview gg-article-body" data-editor-preview hidden aria-live="polite"></div>
                @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">
                    Cách đoạn: để 1 dòng trống. <code>**chữ đậm**</code> · <code>## Tiêu đề mục</code> · <code>- gạch đầu dòng</code> ·
                    ảnh: <code>![mô tả](đường dẫn)</code>. Mã HTML gõ tay sẽ bị bỏ. Bấm <strong>Xem trước</strong> trên thanh công cụ để xem bài (cả ảnh) như khách thấy.
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="gg-panel gg-panel-body mb-3">
            <div class="fw-semibold mb-2">Đăng bài</div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="status" id="st-draft" value="draft" @checked($status === 'draft')>
                <label class="form-check-label" for="st-draft">Lưu nháp <span class="text-muted small">(chưa hiện ra ngoài)</span></label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="status" id="st-pub" value="published" @checked($status === 'published')>
                <label class="form-check-label" for="st-pub">Đăng</label>
            </div>
            <label class="form-label small mb-1" for="published_at">Giờ đăng (giờ Việt Nam) <span class="text-muted">— để trống = đăng ngay; chọn giờ tương lai = hẹn giờ</span></label>
            <input type="datetime-local" id="published_at" name="published_at" value="{{ $publishedAt }}" class="form-control @error('published_at') is-invalid @enderror">
            @error('published_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
            @if($article->exists)
                <div class="small mt-2">Hiện tại: <span class="badge {{ ['Nháp' => 'bg-secondary', 'Hẹn giờ' => 'bg-warning text-dark', 'Đã đăng' => 'bg-success'][$article->statusLabel()] }}">{{ $article->statusLabel() }}</span></div>
            @endif
            <button class="btn btn-green w-100 mt-3"><i class="fa fa-floppy-disk"></i> Lưu bài</button>
        </div>

        <div class="gg-panel gg-panel-body mb-3">
            <div class="fw-semibold mb-2">Ảnh bìa</div>
            @if($article->image)
                <img src="{{ $article->image }}" alt="Ảnh bìa hiện tại" class="img-fluid rounded-3 mb-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="remove_cover">
                    <label class="form-check-label small" for="remove_cover">Bỏ ảnh bìa</label>
                </div>
            @endif
            <label class="form-label small mb-1" for="cover">Tải ảnh lên (JPG, PNG, WEBP, GIF · tối đa 4MB)</label>
            <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control @error('cover') is-invalid @enderror" data-cover-input>
            <img alt="Ảnh bìa sẽ dùng" class="img-fluid rounded-3 mt-2" data-cover-preview hidden>
            @error('cover') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <label class="form-label small mb-1 mt-2" for="image_url">hoặc dán link ảnh (https://…)</label>
            <input id="image_url" name="image_url" value="{{ old('image_url') }}" placeholder="https://..." class="form-control form-control-sm @error('image_url') is-invalid @enderror">
            @error('image_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="gg-panel gg-panel-body">
            <div class="fw-semibold mb-2">Cây liên quan <span class="fw-normal text-muted small">(hiện cuối bài)</span></div>
            <div class="gg-checklist">
                @foreach($plants as $p)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="plant_ids[]" value="{{ $p->id }}" id="pl-{{ $p->id }}" @checked(in_array($p->id, $selectedPlants, true))>
                        <label class="form-check-label" for="pl-{{ $p->id }}">{{ $p->name }}</label>
                    </div>
                @endforeach
            </div>
            @error('plant_ids.*') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
<script src="{{ asset('js/article-editor.js') }}?v={{ @filemtime(public_path('js/article-editor.js')) }}"></script>

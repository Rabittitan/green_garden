@extends('layouts.admin')
@section('title', 'Slide trang chủ')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h4 class="text-green fw-bold mb-0"><i class="fa fa-images"></i> Slide trang chủ</h4>
    @if($slides->count() < \App\Models\HomeSlide::MAX)
        <a href="{{ route('admin.slides.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Thêm slide</a>
    @endif
</div>
<p class="small text-muted">Trang chủ chạy lần lượt các slide <strong>đang bật</strong> theo số thứ tự (nhỏ trước). Nên có khoảng 5 slide;
    tối đa {{ \App\Models\HomeSlide::MAX }}. Ảnh ngang, khuyên dùng 1600×600 px. Chưa bật slide nào thì trang chủ hiện banner mặc định.</p>

<div class="row g-3">
    @forelse($slides as $s)
        <div class="col-md-6 col-xl-4">
            <div class="gg-panel h-100 overflow-hidden {{ $s->is_active ? '' : 'opacity-50' }}">
                <div class="gg-slide-thumb"><img src="{{ $s->image }}" alt="{{ $s->title ?? 'Slide' }}" loading="lazy"></div>
                <div class="gg-panel-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-bold">{{ $s->title ?: '(Không có tiêu đề)' }}</div>
                            @if($s->subtitle)<div class="small text-muted">{{ $s->subtitle }}</div>@endif
                        </div>
                        <span class="badge {{ $s->is_active ? 'bg-success' : 'bg-secondary' }} text-nowrap">{{ $s->is_active ? 'Đang bật' : 'Đang tắt' }}</span>
                    </div>
                    <div class="small mt-2">Thứ tự: <strong>{{ $s->sort_order }}</strong>
                        @if($s->link_url) · Link: <span class="text-muted text-break">{{ $s->link_url }}</span>@endif</div>
                    <div class="d-flex gap-2 mt-3">
                        <a href="{{ route('admin.slides.edit', $s) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-pen"></i> Sửa</a>
                        <form action="{{ route('admin.slides.destroy', $s) }}" method="POST" onsubmit="return confirm('Xóa slide này?')">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i> Xóa</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="alert alert-info mb-0">Chưa có slide nào. Bấm <strong>Thêm slide</strong> để tạo.</div></div>
    @endforelse
</div>
@endsection

@extends('layouts.admin')
@section('title', 'Tin tức')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="text-green fw-bold mb-0"><i class="fa fa-newspaper"></i> Tin tức</h4>
    <a href="{{ route('admin.articles.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Viết bài mới</a>
</div>

<div class="mb-2 d-flex flex-wrap gap-1">
    @foreach(['' => 'Tất cả', 'published' => 'Đang hiện', 'scheduled' => 'Hẹn giờ', 'draft' => 'Nháp'] as $v => $l)
        <a href="{{ route('admin.articles.index', array_filter(['status' => $v])) }}"
           class="btn btn-sm {{ (string) request('status') === $v ? 'btn-success' : 'btn-outline-success' }}">{{ $l }}</a>
    @endforeach
</div>
<form class="mb-3 d-flex flex-wrap gap-2" method="GET">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:300px" placeholder="Tìm theo tiêu đề">
    <button class="btn btn-sm btn-outline-secondary">Tìm</button>
</form>

<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle gg-table-stack">
    <thead class="table-success"><tr><th style="width:84px">Ảnh</th><th>Tiêu đề</th><th>Trạng thái</th><th>Giờ đăng</th><th>Người viết</th><th>Bình luận</th><th></th></tr></thead>
    <tbody>
        @forelse($articles as $a)
            <tr>
                <td data-label="Ảnh">@if($a->image)<img src="{{ $a->image }}" alt="" class="gg-thumb" loading="lazy">@else<span class="gg-thumb gg-thumb-empty"><i class="fa fa-image"></i></span>@endif</td>
                <td data-label="Tiêu đề"><a href="{{ route('admin.articles.edit', $a) }}" class="fw-semibold text-decoration-none">{{ $a->title }}</a>
                    <div class="small text-muted text-truncate" style="max-width:360px">{{ $a->summary }}</div></td>
                <td data-label="Trạng thái"><span class="badge {{ ['Nháp' => 'bg-secondary', 'Hẹn giờ' => 'bg-warning text-dark', 'Đã đăng' => 'bg-success'][$a->statusLabel()] }}">{{ $a->statusLabel() }}</span></td>
                <td data-label="Giờ đăng">{{ $a->published_at ? \App\Support\Format::datetime($a->published_at) : '—' }}</td>
                <td data-label="Người viết">{{ $a->author->name ?? '—' }}</td>
                <td data-label="Bình luận">{{ $a->comments_count }}</td>
                <td data-label="" class="text-nowrap">
                    <a href="{{ route('admin.articles.show', $a) }}" class="btn btn-sm btn-outline-secondary">Xem trước</a>
                    <a href="{{ route('admin.articles.edit', $a) }}" class="btn btn-sm btn-outline-success">Sửa</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" data-label="" class="text-center text-muted py-4">Chưa có bài viết nào.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
{{ $articles->links() }}
@endsection

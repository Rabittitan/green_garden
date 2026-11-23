@extends('layouts.admin')
@section('title', 'Xem trước bài viết')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <span class="badge {{ ['Nháp' => 'bg-secondary', 'Hẹn giờ' => 'bg-warning text-dark', 'Đã đăng' => 'bg-success'][$article->statusLabel()] }}">{{ $article->statusLabel() }}</span>
        <span class="small text-muted">Xem trước — khách thấy bài này giống hệt ở trang tin tức{{ $article->statusLabel() === 'Đã đăng' ? '' : ' sau khi bài được đăng' }}.</span>
    </div>
    <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-green"><i class="fa fa-pen"></i> Sửa bài</a>
</div>
<div class="gg-panel gg-panel-body">
    <div class="mx-auto" style="max-width: 760px">
        <p class="lead text-secondary">{{ $article->summary }}</p>
        @include('news._article')
    </div>
</div>
@endsection

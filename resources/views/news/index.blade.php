@extends('layouts.app')
@section('title', 'Bí Quyết Chăm Cây')

@section('content')
<div class="mb-4">
    <span class="gg-eyebrow">Tin tức</span>
    <h1 class="h2 fw-bold mb-1"><i class="fa fa-book-open text-green"></i> Bí Quyết Chăm Cây</h1>
    <p class="text-muted mb-0">Mẹo hay giúp cây cảnh của bạn luôn xanh tốt.</p>
</div>

<div class="row g-3">
    @forelse($articles as $article)
        <div class="col-md-6 col-lg-4">@include('partials.article-card', ['article' => $article])</div>
    @empty
        <div class="col-12"><div class="text-center bg-white border rounded-4 py-5 text-muted">Chưa có bài viết nào.</div></div>
    @endforelse
</div>

{{-- Phân trang --}}
<div class="mt-4">{{ $articles->links() }}</div>
@endsection

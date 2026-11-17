@extends('layouts.app')
@section('title', 'Bí Quyết Chăm Cây')

@section('content')
<h4 class="text-green fw-bold mb-1"><i class="fa fa-book-open"></i> Bí Quyết Chăm Cây</h4>
<p class="text-muted mb-4">Mẹo hay giúp cây cảnh của bạn luôn xanh tốt.</p>

<div class="row g-3">
    @forelse($articles as $article)
        <div class="col-md-4">@include('partials.article-card', ['article' => $article])</div>
    @empty
        <div class="col-12"><div class="alert alert-info">Chưa có bài viết nào.</div></div>
    @endforelse
</div>

{{-- Phân trang --}}
<div class="mt-4">{{ $articles->links() }}</div>
@endsection
@extends('layouts.app')
@section('title', $article->title)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <nav class="mb-3"><a href="{{ route('news.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Bí Quyết Chăm Cây</a></nav>
        <header class="gg-article-head mb-3">
            <h1>{{ $article->title }}</h1>
            <small class="text-muted"><i class="fa fa-calendar"></i> {{ $article->created_at->format('d/m/Y') }}</small>
        </header>

        <div class="gg-article-cover my-3">
            <img src="{{ $article->image }}" alt="{{ $article->title }}"
                 onerror="this.onerror=null;this.src='https://placehold.co/800x400/e8f5e9/2e7d32?text=GreenGarden'">
        </div>

        {{-- e() chống chèn mã độc, nl2br giữ xuống dòng giữa các đoạn --}}
        <div class="gg-article-body">{!! nl2br(e($article->content)) !!}</div>
    </div>
</div>

<div class="row justify-content-center mt-4"><div class="col-lg-8">
    @include('partials.comments', ['comments' => $comments, 'postRoute' => route('comments.article', $article->id), 'title' => 'Bình luận', 'placeholder' => 'Chia sẻ ý kiến hoặc kinh nghiệm của bạn...'])
</div></div>

{{-- Bài khác --}}
@if($others->count())
    <div class="gg-section-head"><h2>Bài viết khác</h2><a href="{{ route('news.index') }}">Xem tất cả <i class="fa fa-arrow-right"></i></a></div>
    <div class="row g-3">
        @foreach($others as $item)
            <div class="col-md-4">@include('partials.article-card', ['article' => $item])</div>
        @endforeach
    </div>
@endif
@endsection

@extends('layouts.app')
@section('title', $article->title)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <nav class="mb-3"><a href="{{ route('news.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Bí Quyết Chăm Cây</a></nav>
        @include('news._article')
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

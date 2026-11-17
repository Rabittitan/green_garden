@extends('layouts.app')
@section('title', $article->title)

@section('content')
<nav class="mb-3"><a href="{{ route('news.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Bí Quyết Chăm Cây</a></nav>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h2 class="fw-bold">{{ $article->title }}</h2>
        <small class="text-muted"><i class="fa fa-calendar"></i> {{ $article->created_at->format('d/m/Y') }}</small>

        <img src="{{ $article->image }}" class="img-fluid rounded-3 shadow-sm w-100 my-3" alt="{{ $article->title }}"
             onerror="this.onerror=null;this.src='https://placehold.co/800x400/e8f5e9/2e7d32?text=GreenLife'">

        {{-- e() chống chèn mã độc, nl2br giữ xuống dòng giữa các đoạn --}}
        <div class="fs-5 text-secondary" style="line-height:1.8">{!! nl2br(e($article->content)) !!}</div>
    </div>
</div>

{{-- Bài khác --}}
@if($others->count())
    <h4 class="text-green fw-bold mt-5 mb-3">Bài viết khác</h4>
    <div class="row g-3">
        @foreach($others as $item)
            <div class="col-md-4">@include('partials.article-card', ['article' => $item])</div>
        @endforeach
    </div>
@endif
@endsection
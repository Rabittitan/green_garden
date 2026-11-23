@extends('layouts.admin')
@section('title', 'Sửa bài viết')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="text-green fw-bold mb-0"><a href="{{ route('admin.articles.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> Sửa bài viết</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.articles.show', $article) }}" class="btn btn-outline-secondary"><i class="fa fa-eye"></i> Xem trước</a>
        @if($article->status === 'published' && ! $article->isScheduled())
            <a href="{{ route('news.show', $article->id) }}" class="btn btn-outline-green" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square"></i> Xem trên trang</a>
        @endif
    </div>
</div>
<form action="{{ route('admin.articles.update', $article) }}" method="POST" enctype="multipart/form-data" id="article-form">
    @csrf @method('PUT')
    @include('admin.articles._form')
</form>
<form action="{{ route('admin.articles.destroy', $article) }}" method="POST" class="mt-3" onsubmit="return confirm('Xóa hẳn bài viết này?')">
    @csrf @method('DELETE')
    <button class="btn btn-link text-danger p-0"><i class="fa fa-trash"></i> Xóa bài</button>
    <span class="small text-muted">(bài đã có bình luận thì chỉ chuyển về Nháp được)</span>
</form>
@endsection

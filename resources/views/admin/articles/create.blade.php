@extends('layouts.admin')
@section('title', 'Viết bài mới')

@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.articles.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> Viết bài mới</h4>
<form action="{{ route('admin.articles.store') }}" method="POST" enctype="multipart/form-data" id="article-form">
    @csrf
    @include('admin.articles._form')
</form>
@endsection

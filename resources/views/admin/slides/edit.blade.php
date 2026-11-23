@extends('layouts.admin')
@section('title', 'Sửa slide')

@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.slides.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> Sửa slide</h4>
<form action="{{ route('admin.slides.update', $slide) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('admin.slides._form')
</form>
@endsection

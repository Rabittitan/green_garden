@extends('layouts.admin')
@section('title', 'Thêm slide')

@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.slides.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> Thêm slide</h4>
<form action="{{ route('admin.slides.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('admin.slides._form')
</form>
@endsection

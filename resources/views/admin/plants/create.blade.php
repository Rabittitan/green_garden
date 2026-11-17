@extends('layouts.admin')
@section('title', 'Thêm cây mới')

@section('content')
<h4 class="text-green fw-bold mb-3">Thêm cây mới</h4>
<form action="{{ route('admin.plants.store') }}" method="POST" class="card card-body shadow-sm">
    @csrf
    @include('admin.plants._form')
</form>
@endsection
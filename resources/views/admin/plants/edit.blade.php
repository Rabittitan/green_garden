@extends('layouts.admin')
@section('title', 'Sửa cây')

@section('content')
<h4 class="text-green fw-bold mb-3">Sửa cây: {{ $plant->name }}</h4>
<form action="{{ route('admin.plants.update', $plant->id) }}" method="POST" class="card card-body shadow-sm">
    @csrf
    @method('PUT') {{-- giả lập method PUT để vào hàm update() --}}
    @include('admin.plants._form')
</form>
@endsection
@extends('layouts.admin')
@section('title', 'Sửa nhà cung cấp')
@section('content')
<h4 class="text-green fw-bold mb-3">Sửa nhà cung cấp {{ $supplier->name }}</h4>
<form action="{{ route('admin.suppliers.update', $supplier) }}" method="POST" class="card card-body shadow-sm" style="max-width:600px">@csrf @method('PUT')
    @include('admin.suppliers._fields', ['supplier' => $supplier])
    <div class="form-check mb-3">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $supplier->is_active))>
        <label class="form-check-label" for="is_active">Đang hợp tác</label>
    </div>
    <div><button class="btn btn-green">Lưu</button> <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline-secondary">Hủy</a></div>
</form>
@endsection

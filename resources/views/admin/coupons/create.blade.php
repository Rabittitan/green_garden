@extends('layouts.admin')
@section('title', 'Tạo mã giảm giá')
@section('content')
<h4 class="text-green fw-bold mb-3">Tạo mã giảm giá</h4>
<form action="{{ route('admin.coupons.store') }}" method="POST" class="card card-body shadow-sm">@csrf
    @include('admin.coupons._form')
</form>
@endsection

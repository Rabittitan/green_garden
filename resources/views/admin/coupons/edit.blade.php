@extends('layouts.admin')
@section('title', 'Sửa mã ' . $coupon->code)
@section('content')
<h4 class="text-green fw-bold mb-3">Sửa mã {{ $coupon->code }}</h4>
<form action="{{ route('admin.coupons.update', $coupon) }}" method="POST" class="card card-body shadow-sm">@csrf @method('PUT')
    @include('admin.coupons._form')
</form>
@endsection

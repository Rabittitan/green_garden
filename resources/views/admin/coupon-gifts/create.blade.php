@extends('layouts.admin')
@section('title', 'Thêm luật tặng mã')
@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.coupon-gifts.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> Thêm luật tặng mã</h4>
<form action="{{ route('admin.coupon-gifts.store') }}" method="POST">@csrf @include('admin.coupon-gifts._form')</form>
@endsection

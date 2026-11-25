@extends('layouts.admin')
@section('title', 'Sửa luật tặng mã')
@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.coupon-gifts.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> {{ $rule->name }}</h4>
<form action="{{ route('admin.coupon-gifts.update', $rule) }}" method="POST">@csrf @method('PUT') @include('admin.coupon-gifts._form')</form>
@endsection

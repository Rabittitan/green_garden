@extends('layouts.admin')
@section('title', 'Tạo đợt flash sale')
@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.flash-sales.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> Tạo đợt flash sale</h4>
<form action="{{ route('admin.flash-sales.store') }}" method="POST">@csrf @include('admin.flash-sales._form')</form>
@endsection

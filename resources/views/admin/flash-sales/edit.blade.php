@extends('layouts.admin')
@section('title', 'Sửa đợt flash sale')
@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.flash-sales.show', $sale) }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> Sửa: {{ $sale->name }}</h4>
<form action="{{ route('admin.flash-sales.update', $sale) }}" method="POST">@csrf @method('PUT') @include('admin.flash-sales._form')</form>
@endsection

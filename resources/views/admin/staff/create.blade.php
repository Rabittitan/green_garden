@extends('layouts.admin')
@section('title', 'Thêm nhân viên')

@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.staff.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> Thêm nhân viên</h4>
<form action="{{ route('admin.staff.store') }}" method="POST">@csrf @include('admin.staff._form')</form>
@endsection

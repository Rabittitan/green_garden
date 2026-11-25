@extends('layouts.admin')
@section('title', 'Sửa nhân viên')

@section('content')
<h4 class="text-green fw-bold mb-3"><a href="{{ route('admin.staff.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i></a> {{ $member->name }}</h4>
<form action="{{ route('admin.staff.update', $member) }}" method="POST">@csrf @method('PUT') @include('admin.staff._form')</form>
@endsection

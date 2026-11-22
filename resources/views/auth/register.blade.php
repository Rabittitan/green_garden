@extends('layouts.app')
@section('title', 'Đăng ký')

@section('content')
<div class="row g-0 gg-auth">
    @include('auth._aside', ['title' => 'Tạo tài khoản miễn phí', 'subtitle' => 'Chỉ mất chưa đầy một phút để bắt đầu mua sắm và tích lũy hạng thành viên.'])
    <div class="col-md-7 gg-auth-main">
        <div class="gg-auth-icon"><i class="fa fa-user-plus"></i></div>
        <h1 class="mb-1">Đăng ký tài khoản</h1>
        <p class="text-secondary mb-4">Các mục có dấu <span class="text-danger">*</span> là bắt buộc.</p>
        <form action="{{ route('register.post') }}" method="POST" novalidate>
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold" for="name">Họ tên <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" class="form-control @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="ban@email.com"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="row">
                <div class="col-sm-6 mb-3">
                    <label class="form-label fw-semibold" for="password">Mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" id="password" name="password" data-password autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6 mb-3">
                    <label class="form-label fw-semibold" for="password_confirmation">Nhập lại mật khẩu <span class="text-danger">*</span></label>
                    {{-- Tên bắt buộc là password_confirmation để rule "confirmed" hoạt động --}}
                    <input type="password" id="password_confirmation" name="password_confirmation" data-password autocomplete="new-password" class="form-control">
                </div>
            </div>
            <div class="form-text mt-n2 mb-2">Ít nhất 8 ký tự, có cả chữ và số.</div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="show-password" data-show-password>
                <label class="form-check-label small" for="show-password">Hiện mật khẩu</label>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold" for="phone">Số điện thoại</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="0901234567"
                       class="form-control @error('phone') is-invalid @enderror">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-green w-100">Đăng ký</button>
        </form>
        <p class="text-center mt-4 mb-0">Đã có tài khoản? <a href="{{ route('login') }}" class="text-green fw-semibold">Đăng nhập</a></p>
    </div>
</div>
@endsection

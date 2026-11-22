@extends('layouts.app')
@section('title', 'Đặt mật khẩu mới')

@section('content')
<div class="row g-0 gg-auth">
    @include('auth._aside', ['title' => 'Bảo vệ tài khoản', 'subtitle' => 'Sau khi đặt mật khẩu mới, mọi phiên đăng nhập cũ sẽ được đăng xuất để đảm bảo an toàn.'])
    <div class="col-md-7 gg-auth-main">
        <div class="gg-auth-icon"><i class="fa fa-lock"></i></div>
        <h1 class="mb-4">Đặt mật khẩu mới</h1>
        <form action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="password">Mật khẩu mới</label>
                <input type="password" id="password" name="password" data-password required autocomplete="new-password"
                       class="form-control @error('password') is-invalid @enderror">
                <div class="form-text">Ít nhất 8 ký tự, có cả chữ và số.</div>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-2">
                <label class="form-label fw-semibold" for="password_confirmation">Nhập lại mật khẩu mới</label>
                <input type="password" id="password_confirmation" name="password_confirmation" data-password required autocomplete="new-password" class="form-control">
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="show-password" data-show-password>
                <label class="form-check-label small" for="show-password">Hiện mật khẩu</label>
            </div>
            <button class="btn btn-green w-100">Lưu mật khẩu mới</button>
        </form>
    </div>
</div>
@endsection

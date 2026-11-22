@extends('layouts.app')
@section('title', 'Đăng nhập')

@section('content')
<div class="row g-0 gg-auth">
    @include('auth._aside', ['title' => 'Chào mừng trở lại!', 'subtitle' => 'Đăng nhập để đặt hàng, theo dõi đơn và nhận ưu đãi theo hạng thành viên.'])
    <div class="col-md-7 gg-auth-main">
        <div class="gg-auth-icon"><i class="fa fa-right-to-bracket"></i></div>
        <h1 class="mb-1">Đăng nhập</h1>
        <p class="text-secondary mb-4">Nhập email và mật khẩu tài khoản GreenGarden của bạn.</p>
        <form action="{{ route('login.post') }}" method="POST" novalidate>
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus
                       placeholder="ban@email.com" class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-2">
                <div class="d-flex justify-content-between">
                    <label class="form-label fw-semibold" for="password">Mật khẩu</label>
                    <a href="{{ route('password.request') }}" class="small text-green">Quên mật khẩu?</a>
                </div>
                <input type="password" id="password" name="password" data-password autocomplete="current-password"
                       class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="show-password" data-show-password>
                <label class="form-check-label small" for="show-password">Hiện mật khẩu</label>
            </div>
            <button class="btn btn-green w-100">Đăng nhập</button>
        </form>
        <div class="gg-divider my-4">hoặc</div>
        <p class="text-center mb-0">Chưa có tài khoản? <a href="{{ route('register') }}" class="text-green fw-semibold">Đăng ký</a></p>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Quên mật khẩu')

@section('content')
<div class="row g-0 gg-auth">
    @include('auth._aside', ['title' => 'Đừng lo lắng!', 'subtitle' => 'Chỉ cần email đã đăng ký, bạn sẽ đặt được mật khẩu mới trong vài phút.'])
    <div class="col-md-7 gg-auth-main">
        <div class="gg-auth-icon"><i class="fa fa-key"></i></div>
        <h1 class="mb-1">Quên mật khẩu</h1>
        <p class="text-secondary mb-4">Nhập email đã đăng ký, chúng tôi sẽ gửi liên kết để bạn đặt mật khẩu mới. Liên kết có hiệu lực trong 60 phút.</p>
        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label fw-semibold" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="ban@email.com"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-green w-100"><i class="fa fa-paper-plane me-1"></i> Gửi liên kết đặt lại mật khẩu</button>
        </form>
        <p class="text-center mt-4 mb-0"><a href="{{ route('login') }}" class="text-green fw-semibold"><i class="fa fa-arrow-left"></i> Quay lại đăng nhập</a></p>
    </div>
</div>
@endsection

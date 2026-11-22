@extends('layouts.app')
@section('title', 'Quên mật khẩu')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="text-green fw-bold text-center mb-2"><i class="fa fa-key"></i> Quên mật khẩu</h4>
                <p class="text-secondary text-center small mb-3">Nhập email đã đăng ký, chúng tôi sẽ gửi liên kết để bạn đặt mật khẩu mới.</p>
                <form action="{{ route('password.email') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                               class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-green w-100">Gửi liên kết đặt lại mật khẩu</button>
                </form>
                <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}" class="text-green"><i class="fa fa-arrow-left"></i> Quay lại đăng nhập</a></p>
            </div>
        </div>
    </div>
</div>
@endsection

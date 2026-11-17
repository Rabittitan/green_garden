@extends('layouts.app')
@section('title', 'Đăng nhập')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="text-green fw-bold text-center mb-3"><i class="fa fa-right-to-bracket"></i> Đăng nhập</h4>
                <form action="{{ route('login.post') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-green w-100">Đăng nhập</button>
                </form>
                <p class="text-center mt-3 mb-0">Chưa có tài khoản? <a href="{{ route('register') }}" class="text-green">Đăng ký</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
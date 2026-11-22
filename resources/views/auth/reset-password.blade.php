@extends('layouts.app')
@section('title', 'Đặt mật khẩu mới')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="text-green fw-bold text-center mb-3"><i class="fa fa-lock"></i> Đặt mật khẩu mới</h4>
                <form action="{{ route('password.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"
                               class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu mới</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password"
                               class="form-control @error('password') is-invalid @enderror">
                        <div class="form-text">Ít nhất 8 ký tự, có cả chữ và số.</div>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
                    </div>
                    <button class="btn btn-green w-100">Lưu mật khẩu mới</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

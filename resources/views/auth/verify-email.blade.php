@extends('layouts.app')
@section('title', 'Xác thực email')

@section('content')
<div class="row g-0 gg-auth">
    @include('auth._aside', ['title' => 'Chỉ còn một bước nữa', 'subtitle' => 'Xác thực email giúp bảo vệ tài khoản và đảm bảo bạn nhận được thông báo đơn hàng.'])
    <div class="col-md-7 gg-auth-main text-center text-md-start">
        <div class="gg-auth-icon mx-auto mx-md-0"><i class="fa fa-envelope-circle-check"></i></div>
        <h1 class="mb-3">Xác thực địa chỉ email</h1>
        <p class="text-secondary">
            Chúng tôi đã gửi một liên kết xác thực tới
            <strong>{{ auth()->user()->email }}</strong>.<br>
            Vui lòng mở email và bấm <strong>"Xác thực email"</strong> để có thể đặt hàng.
        </p>
        <p class="small text-muted">Không thấy email? Hãy kiểm tra thư mục <em>Spam / Quảng cáo</em>. Liên kết có hiệu lực trong 60 phút.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start mt-4">
            <form action="{{ route('verification.send') }}" method="POST" class="d-inline">
                @csrf
                <button class="btn btn-green"><i class="fa fa-paper-plane"></i> Gửi lại email xác thực</button>
            </form>
            <a href="{{ route('profile.edit') }}" class="btn btn-outline-green">Sai email? Sửa tại đây</a>
        </div>
    </div>
</div>
@endsection

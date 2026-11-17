@extends('layouts.app')
@section('title', 'Xác thực email')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <div class="fs-1 text-green mb-2"><i class="fa fa-envelope-circle-check"></i></div>
                <h4 class="fw-bold text-green">Xác thực địa chỉ email</h4>
                <p class="text-secondary">
                    Chúng tôi đã gửi một liên kết xác thực tới
                    <strong>{{ auth()->user()->email }}</strong>.<br>
                    Vui lòng mở email và bấm <strong>"Xác thực email"</strong> để có thể đặt hàng.
                </p>
                <p class="small text-muted">Không thấy email? Hãy kiểm tra thư mục <em>Spam / Quảng cáo</em>. Liên kết có hiệu lực trong 60 phút.</p>

                <form action="{{ route('verification.send') }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-green"><i class="fa fa-paper-plane"></i> Gửi lại email xác thực</button>
                </form>
                <a href="{{ route('profile.edit') }}" class="btn btn-outline-green ms-1">Sai email? Sửa tại đây</a>
            </div>
        </div>
    </div>
</div>
@endsection
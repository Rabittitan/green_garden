@extends('layouts.app')
@section('title', 'Hồ sơ của tôi')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-user-circle"></i> Hồ sơ của tôi</h4>

<div class="row g-4">
    {{-- ===== CỘT TRÁI: tóm tắt tài khoản ===== --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body text-center">
                <div class="rounded-circle bg-green text-white d-inline-flex align-items-center justify-content-center fs-2 fw-bold mb-2"
                     style="width:80px;height:80px">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
                <h5 class="fw-bold mb-0">{{ $user->name }}</h5>
                <div class="text-muted small">{{ $user->email }}</div>

                {{-- Trạng thái xác thực email --}}
                @if($user->hasVerifiedEmail())
                    <span class="badge bg-success mt-2"><i class="fa fa-circle-check"></i> Email đã xác thực</span>
                @else
                    <span class="badge bg-warning text-dark mt-2"><i class="fa fa-triangle-exclamation"></i> Email chưa xác thực</span>
                    <form action="{{ route('verification.send') }}" method="POST" class="mt-2">
                        @csrf
                        <button class="btn btn-sm btn-outline-green">Gửi lại email xác thực</button>
                    </form>
                @endif

                <div class="text-muted small mt-2">Thành viên từ @vndt($user->created_at)</div>
                <a href="{{ route('account.membership') }}" class="badge bg-warning text-dark text-decoration-none mt-2"><i class="fa fa-crown"></i> Hạng {{ $user->tier->name }}</a>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>Tổng số đơn</span><strong>{{ $stats['orders'] }}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Đơn hoàn thành</span><strong>{{ $stats['completed'] }}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Tổng chi tiêu (xét hạng)</span>
                    <strong class="text-green">@money($stats['spent'])</strong></li>
            </ul>
        </div>

        {{-- Đơn gần đây --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold d-flex justify-content-between">
                <span>Đơn gần đây</span>
                <a href="{{ route('orders.index') }}" class="small text-green text-decoration-none">Xem tất cả</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($latestOrders as $order)
                    <a href="{{ route('orders.show', $order) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>
                            <strong>{{ $order->code }}</strong><br>
                            <small class="text-muted">@vndt($order->placed_at)</small>
                        </span>
                        <span class="text-end">
                            <span class="badge bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span><br>
                            <small>@money($order->total)</small>
                        </span>
                    </a>
                @empty
                    <li class="list-group-item text-muted">Bạn chưa có đơn hàng nào.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- ===== CỘT PHẢI: form sửa ===== --}}
    <div class="col-lg-8">
        {{-- Thông tin cá nhân --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold"><i class="fa fa-id-card text-green"></i> Thông tin cá nhân</div>
            <div class="card-body">
                <form action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Họ tên *</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                   class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Đổi email thì cần xác thực lại email mới.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                                   class="form-control @error('phone') is-invalid @enderror" placeholder="0901234567">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Địa chỉ giao hàng</label>
                            <div><a href="{{ route('addresses.index') }}" class="btn btn-outline-green"><i class="fa fa-location-dot"></i> Quản lý sổ địa chỉ</a></div>
                        </div>
                    </div>
                    <button class="btn btn-green"><i class="fa fa-floppy-disk"></i> Lưu thay đổi</button>
                </form>
            </div>
        </div>

        {{-- Đổi mật khẩu: lỗi nằm trong error bag 'password' để không lẫn với form trên --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold"><i class="fa fa-key text-green"></i> Đổi mật khẩu</div>
            <div class="card-body">
                <form action="{{ route('profile.password') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu hiện tại *</label>
                        <input type="password" name="current_password" autocomplete="current-password"
                               class="form-control @error('current_password', 'password') is-invalid @enderror">
                        @error('current_password', 'password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mật khẩu mới *</label>
                            <input type="password" name="password" autocomplete="new-password"
                                   class="form-control @error('password', 'password') is-invalid @enderror">
                            @error('password', 'password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Ít nhất 8 ký tự, có cả chữ và số.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nhập lại mật khẩu mới *</label>
                            <input type="password" name="password_confirmation" autocomplete="new-password" class="form-control">
                        </div>
                    </div>
                    <button class="btn btn-outline-green"><i class="fa fa-lock"></i> Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
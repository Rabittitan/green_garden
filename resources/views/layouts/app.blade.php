<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GreenGarden Shop - Cây cảnh phong thủy & nội thất')</title>
    {{-- Bootstrap 5 và FontAwesome qua CDN, không cần cài npm --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root { --green: #2e7d32; --green-dark: #1b5e20; --green-light: #e8f5e9; }
        body { background: #f8faf8; }
        .bg-green { background: var(--green) !important; }
        .text-green { color: var(--green) !important; }
        .btn-green { background: var(--green); color: #fff; border: 0; }
        .btn-green:hover { background: var(--green-dark); color: #fff; }
        .btn-outline-green { border: 1px solid var(--green); color: var(--green); }
        .btn-outline-green:hover { background: var(--green); color: #fff; }
        .plant-card { transition: .2s; border: 0; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .plant-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(46,125,50,.25); }
        .plant-card img { height: 210px; object-fit: cover; }
        .hero { background: linear-gradient(rgba(27,94,32,.75), rgba(46,125,50,.75)),
                url('https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1600&q=80') center/cover; }
        .footer { background: var(--green-dark); color: #c8e6c9; }
    </style>
</head>
<body>

{{-- ===== THANH MENU ===== --}}
<nav class="navbar navbar-expand-lg navbar-dark bg-green sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="{{ route('home') }}"><i class="fa-solid fa-seedling"></i> GreenGarden</a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Trang chủ</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('plants.index') }}">Tất cả cây cảnh</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('news.index') }}">Tin Tức</a></li>
            </ul>

            {{-- Ô tìm kiếm: gửi ?keyword=... sang trang danh sách --}}
            <form class="d-flex me-3" action="{{ route('plants.index') }}" method="GET">
                <input class="form-control form-control-sm me-2" name="keyword" placeholder="Tìm cây..." value="{{ request('keyword') }}">
                <button class="btn btn-sm btn-light"><i class="fa fa-search"></i></button>
            </form>

            <ul class="navbar-nav align-items-lg-center">
                {{-- Giỏ hàng: count() đếm số loại cây trong session --}}
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('cart.index') }}">
                        <i class="fa fa-shopping-cart"></i> Giỏ hàng
                        <span class="badge bg-warning text-dark">{{ $cartCount ?? 0 }}</span>
                    </a>
                </li>

                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fa fa-user"></i> {{ auth()->user()->name }}
                            @if(auth()->user()->tier && auth()->user()->tier->rank > 0)
                                <span class="badge bg-warning text-dark">{{ auth()->user()->tier->name }}</span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if(auth()->user()->isBackOffice())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Trang quản trị</a></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fa fa-id-card me-1"></i> Hồ sơ của tôi</a></li>
                            <li><a class="dropdown-item" href="{{ route('orders.index') }}"><i class="fa fa-receipt me-1"></i> Đơn hàng của tôi</a></li>
                            <li><a class="dropdown-item" href="{{ route('account.membership') }}"><i class="fa fa-crown me-1"></i> Hạng thành viên</a></li>
                            <li><a class="dropdown-item" href="{{ route('addresses.index') }}"><i class="fa fa-location-dot me-1"></i> Sổ địa chỉ</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button class="dropdown-item">Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Đăng nhập</a></li>
                    <li class="nav-item"><a class="btn btn-sm btn-light ms-lg-2" href="{{ route('register') }}">Đăng ký</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

{{-- ===== THÔNG BÁO (flash message) ===== --}}
<div class="container mt-3">
    {{-- Nhắc xác thực email (ẩn ở chính trang xác thực để không lặp) --}}
    @auth
        @if(! auth()->user()->hasVerifiedEmail() && ! request()->routeIs('verification.notice'))
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span><i class="fa fa-envelope"></i> Email <strong>{{ auth()->user()->email }}</strong> chưa được xác thực. Bạn cần xác thực để đặt hàng.</span>
                <form action="{{ route('verification.send') }}" method="POST" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-warning">Gửi lại email xác thực</button>
                </form>
            </div>
        @endif
    @endauth
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check-circle"></i> {{ session('success') }}
            <button class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="fa fa-triangle-exclamation"></i> {{ session('error') }}
            <button class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show"><i class="fa fa-circle-info"></i> {{ session('warning') }}
            <button class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
</div>

{{-- ===== NỘI DUNG RIÊNG TỪNG TRANG ===== --}}
<main class="container py-3" style="min-height: 60vh">
    @yield('content')
</main>

{{-- ===== CHÂN TRANG ===== --}}
<footer class="footer mt-5 py-4">
    <div class="container text-center">
        <h5 class="text-white"><i class="fa-solid fa-seedling"></i> GreenGarden Shop</h5>
        <p class="mb-1">Cây cảnh phong thủy & nội thất - Mang thiên nhiên vào ngôi nhà bạn</p>
        <small>Hotline: 0900 000 001 | Email: lienhe@GreenGarden.test</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
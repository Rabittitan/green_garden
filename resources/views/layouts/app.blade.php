<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2e7d32">
    <title>@yield('title', 'GreenGarden Shop - Cây cảnh phong thủy & nội thất')</title>
    {{-- Bootstrap 5 và FontAwesome qua CDN, không cần cài npm --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    {{-- Giao diện chung (màu, chữ, khoảng cách): public/css/app.css; ?v= đổi khi file đổi để trình duyệt không dùng bản cũ --}}
    <link href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body>

{{-- ===== THANH THÔNG BÁO TRÊN CÙNG ===== --}}
<div class="gg-topbar py-1 d-none d-md-block">
    <div class="container d-flex justify-content-between">
        <span><i class="fa fa-truck-fast me-1"></i> Miễn phí giao hàng cho đơn từ <strong>@money(config('shop.shipping.free_min_order'))</strong></span>
        <span><i class="fa fa-phone me-1"></i> Hotline: <a href="tel:0900000001">0900 000 001</a></span>
    </div>
</div>

{{-- ===== THANH MENU ===== --}}
<nav class="navbar navbar-expand-lg gg-header sticky-top py-2">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}"><span class="gg-logo"><i class="fa-solid fa-seedling"></i></span>GreenGarden</a>

        <div class="d-flex align-items-center gap-1 order-lg-last">
            {{-- Giỏ hàng: count() đếm số loại cây trong session --}}
            <a class="gg-icon-btn" href="{{ route('cart.index') }}" title="Giỏ hàng">
                <i class="fa fa-cart-shopping fs-5"></i>
                <span class="visually-hidden">Giỏ hàng</span>
                <span class="gg-count">{{ $cartCount ?? 0 }}</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none ms-1" data-bs-toggle="collapse" data-bs-target="#menu" aria-label="Mở menu">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-lg-3 ms-lg-3 my-2 my-lg-0">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Trang chủ</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('plants.*') ? 'active' : '' }}" href="{{ route('plants.index') }}">Tất cả cây cảnh</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('news.*') ? 'active' : '' }}" href="{{ route('news.index') }}">Tin Tức</a></li>
            </ul>

            {{-- Ô tìm kiếm: gửi ?keyword=... sang trang danh sách --}}
            <form class="gg-search me-lg-auto mb-2 mb-lg-0" action="{{ route('plants.index') }}" method="GET" role="search">
                <i class="fa fa-magnifying-glass"></i>
                <input class="form-control" name="keyword" placeholder="Tìm cây..." value="{{ request('keyword') }}" aria-label="Tìm cây">
            </form>

            <ul class="navbar-nav align-items-lg-center ms-lg-2">
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                            <span class="gg-avatar">{{ mb_strtoupper(mb_substr(trim(auth()->user()->name), 0, 1)) }}</span>
                            <span class="gg-username d-lg-none d-xl-inline" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</span>
                            @if(auth()->user()->tier && auth()->user()->tier->rank > 0)
                                <span class="badge gg-tier">{{ auth()->user()->tier->name }}</span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if(auth()->user()->isBackOffice())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="fa fa-gauge me-1"></i> Trang quản trị</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fa fa-id-card me-1"></i> Hồ sơ của tôi</a></li>
                            <li><a class="dropdown-item" href="{{ route('orders.index') }}"><i class="fa fa-receipt me-1"></i> Đơn hàng của tôi</a></li>
                            <li><a class="dropdown-item" href="{{ route('account.membership') }}"><i class="fa fa-crown me-1"></i> Hạng thành viên</a></li>
                            <li><a class="dropdown-item" href="{{ route('addresses.index') }}"><i class="fa fa-location-dot me-1"></i> Sổ địa chỉ</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button class="dropdown-item text-danger"><i class="fa fa-right-from-bracket me-1"></i> Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">Đăng nhập</a></li>
                    <li class="nav-item"><a class="btn btn-green btn-sm px-3 ms-lg-1" href="{{ route('register') }}">Đăng ký</a></li>
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
            <button class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="fa fa-triangle-exclamation"></i> {{ session('error') }}
            <button class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show"><i class="fa fa-circle-info"></i> {{ session('warning') }}
            <button class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div>
    @endif
</div>

{{-- ===== NỘI DUNG RIÊNG TỪNG TRANG ===== --}}
<main class="container py-3" style="min-height: 60vh">
    @yield('content')
</main>

{{-- ===== CHÂN TRANG ===== --}}
<footer class="gg-footer mt-5 pt-5">
    <div class="container">
        <div class="row g-4 pb-4">
            <div class="col-lg-4">
                <h5 class="text-white fw-bold"><i class="fa-solid fa-seedling"></i> GreenGarden Shop</h5>
                <p class="mb-0">Cây cảnh phong thủy & nội thất - Mang thiên nhiên vào ngôi nhà bạn</p>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Mua sắm</h6>
                <ul class="list-unstyled small mb-0">
                    <li><a href="{{ route('plants.index') }}">Tất cả cây cảnh</a></li>
                    <li><a href="{{ route('news.index') }}">Tin tức</a></li>
                    <li><a href="{{ route('cart.index') }}">Giỏ hàng</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h6>Tài khoản</h6>
                <ul class="list-unstyled small mb-0">
                    <li><a href="{{ route('orders.index') }}">Đơn hàng của tôi</a></li>
                    <li><a href="{{ route('account.membership') }}">Hạng thành viên</a></li>
                    <li><a href="{{ route('password.request') }}">Quên mật khẩu</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6>Liên hệ</h6>
                <ul class="list-unstyled small mb-0">
                    <li><i class="fa fa-phone me-2"></i>Hotline: 0900 000 001</li>
                    <li><i class="fa fa-envelope me-2"></i>Email: lienhe@GreenGarden.test</li>
                    <li><i class="fa fa-truck-fast me-2"></i>Miễn phí giao hàng từ @money(config('shop.shipping.free_min_order'))</li>
                </ul>
            </div>
        </div>
        <div class="gg-footer-bottom py-3 text-center">© {{ date('Y') }} GreenGarden Shop. Bảo lưu mọi quyền.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Ô "Hiện mật khẩu": bật/tắt hiển thị mọi ô mật khẩu trong cùng form
    document.querySelectorAll('[data-show-password]').forEach(function (box) {
        box.addEventListener('change', function () {
            box.form.querySelectorAll('input[data-password]').forEach(function (i) { i.type = box.checked ? 'text' : 'password'; });
        });
    });
</script>
</body>
</html>

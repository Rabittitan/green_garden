<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản trị') - GreenGarden Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    {{-- Dùng chung biến màu / chữ với cửa hàng: public/css/app.css --}}
    <link href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body class="gg-admin">
@php
    $me = auth()->user();
    $isAdmin = $me->isAdmin();
    // [route, mẫu route để tô sáng, icon, nhãn, quyền cần có]. Nhân viên chỉ thấy mục được cấp quyền (trang Nhân viên);
    // nhóm "Quản trị" chỉ hiện với quản trị viên (nhân viên không thấy cả tiêu đề nhóm). Nhóm rỗng thì ẩn tiêu đề.
    $groups = [
        'Bán hàng' => [
            ['admin.dashboard', 'admin.dashboard', 'fa-chart-line', 'Thống kê', 'reports'],
            ['admin.defects.report', 'admin.defects.report', 'fa-chart-pie', 'Báo cáo hàng lỗi', 'reports'],
            ['admin.orders.index', 'admin.orders.*', 'fa-receipt', 'Đơn hàng', 'orders'],
            ['admin.reviews.index', 'admin.reviews.*', 'fa-star', 'Đánh giá', 'reviews'],
            ['admin.comments.index', 'admin.comments.*', 'fa-comments', 'Hỏi đáp', 'reviews'],
            ['admin.articles.index', 'admin.articles.*', 'fa-newspaper', 'Tin tức', 'content'],
            ['admin.slides.index', 'admin.slides.*', 'fa-images', 'Slide trang chủ', 'content'],
            ['admin.contact-channels.index', 'admin.contact-channels.*', 'fa-comment-dots', 'Kênh liên hệ', 'content'],
            ['admin.flash-sales.index', 'admin.flash-sales.*', 'fa-bolt', 'Flash sale', 'flash_sales'],
        ],
        'Kho & nhập hàng' => [
            ['admin.plants.index', 'admin.plants.*', 'fa-leaf', 'Cây cảnh', 'catalog'],
            ['admin.inventory.index', 'admin.inventory.*', 'fa-warehouse', 'Kho', 'inventory'],
            ['admin.units.index', 'admin.units.index', 'fa-qrcode', 'Tra cứu chậu', 'inventory'],
            ['admin.defect-reports.index', 'admin.defect-reports.*', 'fa-triangle-exclamation', 'Phiếu báo lỗi', 'inventory'],
            ['admin.stock-counts.index', 'admin.stock-counts.*', 'fa-clipboard-check', 'Phiếu kiểm kê', 'admin'],
            ['admin.purchase-requests.index', 'admin.purchase-requests.*', 'fa-clipboard-list', 'Yêu cầu nhập', 'purchasing'],
            ['admin.goods-receipts.index', 'admin.goods-receipts.*', 'fa-truck-ramp-box', 'Phiếu nhập kho', 'purchasing'],
            ['admin.suppliers.index', 'admin.suppliers.*', 'fa-handshake', 'Nhà cung cấp', 'purchasing'],
        ],
        'Quản trị' => [
            ['admin.coupons.index', 'admin.coupons.*', 'fa-ticket', 'Mã giảm giá', 'admin'],
            ['admin.customers.index', 'admin.customers.*', 'fa-users', 'Khách hàng', 'admin'],
            ['admin.tiers.index', 'admin.tiers.*', 'fa-crown', 'Hạng thành viên', 'admin'],
            ['admin.shipping-zones.index', 'admin.shipping-zones.*', 'fa-truck-fast', 'Phí vận chuyển', 'admin'],
            ['admin.staff.index', 'admin.staff.*', 'fa-id-badge', 'Nhân viên', 'admin'],
        ],
    ];
    $groups = array_filter(array_map(fn ($items) => array_values(array_filter($items,
        fn ($i) => $i[4] === 'admin' ? $isAdmin : $me->hasPermission($i[4]))), $groups));
@endphp
<div class="gg-admin-shell">
    {{-- ===== MENU TRÁI (điện thoại: trượt ra khi bấm nút menu) ===== --}}
    <aside class="gg-admin-sidebar" id="admin-sidebar">
        <a href="{{ route('admin.dashboard') }}" class="gg-admin-brand"><span class="gg-logo"><i class="fa-solid fa-seedling"></i></span> GreenGarden Admin</a>
        {{-- request()->routeIs(...) để tô sáng menu đang mở --}}
        @foreach($groups as $group => $items)
            <div class="gg-admin-group">{{ $group }}</div>
            @foreach($items as [$route, $pattern, $icon, $label])
                <a href="{{ route($route) }}" class="gg-admin-link {{ request()->routeIs($pattern) ? 'active' : '' }}"><i class="fa {{ $icon }}"></i> {{ $label }}</a>
            @endforeach
        @endforeach
        <div class="gg-admin-group">Khác</div>
        <a href="{{ route('home') }}" class="gg-admin-link"><i class="fa fa-store"></i> Xem cửa hàng</a>
        <form action="{{ route('logout') }}" method="POST" class="mb-3">
            @csrf
            <button class="gg-admin-link"><i class="fa fa-right-from-bracket"></i> Đăng xuất</button>
        </form>
    </aside>

    {{-- ===== NỘI DUNG ===== --}}
    <div class="gg-admin-main">
        <header class="gg-admin-top d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-light d-lg-none" id="admin-menu-toggle" aria-label="Mở menu"><i class="fa fa-bars"></i></button>
                <span class="fw-bold">@yield('title', 'Quản trị')</span>
            </div>
            <div class="d-flex align-items-center gap-2 small">
                <span class="gg-avatar">{{ mb_strtoupper(mb_substr(trim(auth()->user()->name), 0, 1)) }}</span>
                <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                <span class="badge gg-role">{{ auth()->user()->role->label() }}</span>
            </div>
        </header>
        <main class="gg-admin-content">
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
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Điện thoại: bật/tắt menu trái; bấm ra ngoài menu thì đóng
    (function () {
        var bar = document.getElementById('admin-sidebar'), btn = document.getElementById('admin-menu-toggle');
        btn && btn.addEventListener('click', function (e) { e.stopPropagation(); bar.classList.toggle('show'); });
        document.addEventListener('click', function (e) { if (bar.classList.contains('show') && !bar.contains(e.target)) bar.classList.remove('show'); });
    })();
</script>
@stack('scripts')
</body>
</html>

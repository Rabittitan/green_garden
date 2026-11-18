<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản trị') - GreenLife Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root { --green: #2e7d32; --green-dark: #1b5e20; }
        body { background: #f4f6f4; }
        .sidebar { background: var(--green-dark); min-height: 100vh; }
        .sidebar a { color: #c8e6c9; text-decoration: none; display: block; padding: 12px 20px; }
        .sidebar a:hover, .sidebar a.active { background: var(--green); color: #fff; }
        .btn-green { background: var(--green); color: #fff; border: 0; }
        .btn-green:hover { background: var(--green-dark); color: #fff; }
        .text-green { color: var(--green) !important; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        {{-- ===== MENU TRÁI ===== --}}
        <div class="col-md-2 p-0 sidebar">
            <div class="text-white fw-bold fs-5 p-3 border-bottom border-success">
                <i class="fa-solid fa-seedling"></i> GreenLife Admin
            </div>
            {{-- request()->routeIs(...) để tô sáng menu đang mở --}}
            @php $isAdmin = auth()->user()->isAdmin(); @endphp
            @foreach(array_filter([
                ['admin.dashboard', 'admin.dashboard', 'fa-chart-line', 'Thống kê', true],
                ['admin.orders.index', 'admin.orders.*', 'fa-receipt', 'Đơn hàng', true],
                ['admin.plants.index', 'admin.plants.*', 'fa-leaf', 'Cây cảnh', true],
                ['admin.inventory.index', 'admin.inventory.*', 'fa-warehouse', 'Kho', true],
                ['admin.purchase-requests.index', 'admin.purchase-requests.*', 'fa-clipboard-list', 'Yêu cầu nhập', true],
                ['admin.goods-receipts.index', 'admin.goods-receipts.*', 'fa-truck-ramp-box', 'Phiếu nhập kho', true],
                ['admin.suppliers.index', 'admin.suppliers.*', 'fa-handshake', 'Nhà cung cấp', true],
                ['admin.reviews.index', 'admin.reviews.*', 'fa-star', 'Đánh giá', true],
                ['admin.comments.index', 'admin.comments.*', 'fa-comments', 'Hỏi đáp', true],
                ['admin.coupons.index', 'admin.coupons.*', 'fa-ticket', 'Mã giảm giá', $isAdmin],
                ['admin.customers.index', 'admin.customers.*', 'fa-users', 'Khách hàng', $isAdmin],
                ['admin.tiers.index', 'admin.tiers.*', 'fa-crown', 'Hạng thành viên', $isAdmin],
            ], fn ($m) => $m[4]) as [$route, $pattern, $icon, $label])
                <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}"><i class="fa {{ $icon }} me-2"></i> {{ $label }}</a>
            @endforeach
            <hr class="border-success">
            <a href="{{ route('home') }}"><i class="fa fa-store me-2"></i> Xem cửa hàng</a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="btn btn-link w-100 text-start px-4" style="color:#c8e6c9;text-decoration:none">
                    <i class="fa fa-right-from-bracket me-2"></i> Đăng xuất</button>
            </form>
        </div>

        {{-- ===== NỘI DUNG ===== --}}
        <div class="col-md-10 p-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
                    <button class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
                    <button class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show">{{ session('warning') }}
                    <button class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            @yield('content')
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
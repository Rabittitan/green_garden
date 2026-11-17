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
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fa fa-chart-line me-2"></i> Thống kê</a>
            <a href="{{ route('admin.plants.index') }}" class="{{ request()->routeIs('admin.plants.*') ? 'active' : '' }}">
                <i class="fa fa-leaf me-2"></i> Quản lý cây cảnh</a>
            <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                <i class="fa fa-receipt me-2"></i> Quản lý đơn hàng</a>
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

            @yield('content')
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
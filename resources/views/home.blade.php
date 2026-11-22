@extends('layouts.app')
@section('title', 'GreenGarden Shop - Trang chủ')

@php
    // Biểu tượng cho từng mệnh (chỉ là hình minh họa, danh sách mệnh vẫn lấy từ Plant::ELEMENTS)
    $elementIcons = ['kim' => 'fa-coins', 'moc' => 'fa-tree', 'thuy' => 'fa-droplet', 'hoa' => 'fa-fire', 'tho' => 'fa-mountain'];
@endphp

@section('content')
{{-- Banner --}}
<section class="hero gg-hero text-white mb-4">
    <div class="row">
        <div class="col-lg-8 col-xl-7">
            <span class="gg-eyebrow mb-2">GreenGarden Shop</span>
            <h1 class="mb-3">Cây Cảnh Phong Thủy & Nội Thất</h1>
            <p class="lead mb-4">Chọn cây hợp mệnh - Đón tài lộc, thanh lọc không khí</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                <a href="{{ route('plants.index') }}" class="btn btn-light text-green">Khám phá ngay <i class="fa fa-arrow-right ms-1"></i></a>
                <a href="#chon-theo-menh" class="btn btn-outline-light">Chọn cây theo mệnh</a>
            </div>
        </div>
    </div>
</section>

{{-- Cam kết: số liệu lấy từ cấu hình shop --}}
<div class="gg-usp mb-2">
    <div class="gg-usp-item"><i class="fa fa-truck-fast"></i>
        <div><strong>Miễn phí giao hàng</strong><br><small>Cho đơn từ @money(config('shop.shipping.free_min_order'))</small></div></div>
    <div class="gg-usp-item"><i class="fa fa-rotate-left"></i>
        <div><strong>Đổi trả {{ config('shop.return_window_days') }} ngày</strong><br><small>Kể từ khi nhận hàng</small></div></div>
    <div class="gg-usp-item"><i class="fa fa-hand-holding-dollar"></i>
        <div><strong>Thanh toán khi nhận hàng</strong><br><small>Nhận cây, kiểm tra rồi mới trả tiền</small></div></div>
</div>

{{-- Chọn cây theo mệnh --}}
<div class="gg-section-head" id="chon-theo-menh">
    <div><span class="gg-eyebrow">Phong thủy</span><h2><i class="fa fa-yin-yang text-green"></i> Chọn cây theo mệnh của bạn</h2></div>
</div>
<div class="gg-elements mb-2">
    @foreach(\App\Models\Plant::ELEMENTS as $key => $label)
        <a href="{{ route('plants.index', ['element' => $key]) }}" class="gg-element gg-el-{{ $key }}">
            <i class="fa {{ $elementIcons[$key] ?? 'fa-leaf' }}"></i>{{ $label }}
        </a>
    @endforeach
</div>

{{-- Danh mục --}}
<div class="gg-section-head">
    <div><span class="gg-eyebrow">Danh mục</span><h2><i class="fa fa-layer-group text-green"></i> Danh mục</h2></div>
    <a href="{{ route('plants.index') }}">Tất cả <i class="fa fa-arrow-right"></i></a>
</div>
<div class="row g-3 mb-2">
    @foreach($categories as $cat)
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('plants.index', ['category' => $cat->id]) }}" class="gg-cat">
                <i class="fa-solid fa-leaf"></i>
                <div class="fw-semibold">{{ $cat->name }}</div>
                <small>{{ $cat->plants_count }} cây</small>
            </a>
        </div>
    @endforeach
</div>

{{-- Cây mới --}}
<div class="gg-section-head">
    <div><span class="gg-eyebrow">Mới nhất</span><h2><i class="fa fa-star text-green"></i> Cây mới về</h2></div>
    <a href="{{ route('plants.index') }}">Xem tất cả cây cảnh <i class="fa fa-arrow-right"></i></a>
</div>
<div class="row g-3">
    @foreach($newPlants as $plant)
        <div class="col-6 col-md-4 col-lg-3">@include('partials.plant-card', ['plant' => $plant])</div>
    @endforeach
</div>

{{-- Bí Quyết Chăm Cây: 3 bài mới nhất --}}
<div class="gg-section-head">
    <div><span class="gg-eyebrow">Tin tức</span><h2><i class="fa fa-book-open text-green"></i> Bí Quyết Chăm Cây</h2></div>
    <a href="{{ route('news.index') }}">Xem tất cả <i class="fa fa-arrow-right"></i></a>
</div>
<div class="row g-3">
    @foreach($articles as $article)
        <div class="col-md-4">@include('partials.article-card', ['article' => $article])</div>
    @endforeach
</div>
@endsection

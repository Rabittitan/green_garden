@extends('layouts.app')
@section('title', 'GreenGarden Shop - Trang chủ')

@php
    // Biểu tượng cho từng mệnh (chỉ là hình minh họa, danh sách mệnh vẫn lấy từ Plant::ELEMENTS)
    $elementIcons = ['kim' => 'fa-coins', 'moc' => 'fa-tree', 'thuy' => 'fa-droplet', 'hoa' => 'fa-fire', 'tho' => 'fa-mountain'];
@endphp

@section('content')
{{-- Slide đầu trang (Admin → Slide trang chủ). Chưa bật slide nào: hiện banner mặc định.
     Slider tự viết (public/js/slider.js + CSS cuộn bắt điểm): không cần CDN; không có JS vẫn vuốt / cuộn được. --}}
@if($slides->isNotEmpty())
    <section class="gg-slider mb-4" data-gg-slider aria-roledescription="carousel" aria-label="Ảnh nổi bật">
        <div class="gg-slider-track" data-track tabindex="0">
            @foreach($slides as $i => $slide)
                <div class="gg-slide" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ $slides->count() }}">
                    <img src="{{ $slide->image }}" alt="{{ $slide->title ?? 'Ảnh nổi bật '.($i + 1) }}" @if($i > 0) loading="lazy" @endif
                         onerror="this.onerror=null;this.src='https://placehold.co/1600x600/2e7d32/ffffff?text=GreenGarden'">
                    @if($slide->title || $slide->subtitle || $slide->link_url)
                        <div class="gg-slide-caption">
                            @if($slide->title)<h2>{{ $slide->title }}</h2>@endif
                            @if($slide->subtitle)<p>{{ $slide->subtitle }}</p>@endif
                            @if($slide->link_url)
                                <a href="{{ $slide->link_url }}" class="btn btn-light text-green">{{ $slide->button_label ?: 'Xem ngay' }} <i class="fa fa-arrow-right ms-1"></i></a>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @if($slides->count() > 1)
            <button type="button" class="gg-slider-nav prev" data-prev aria-label="Ảnh trước"><i class="fa fa-chevron-left"></i></button>
            <button type="button" class="gg-slider-nav next" data-next aria-label="Ảnh sau"><i class="fa fa-chevron-right"></i></button>
            <div class="gg-slider-dots" data-dots>
                @foreach($slides as $i => $slide)
                    <button type="button" aria-label="Ảnh {{ $i + 1 }}" @if($i === 0) aria-current="true" @endif></button>
                @endforeach
            </div>
        @endif
    </section>
@else
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

@endif

{{-- Cam kết: số liệu lấy từ cấu hình shop --}}
<div class="gg-usp mb-2">
    <div class="gg-usp-item"><i class="fa fa-truck-fast"></i>
        <div><strong>Miễn phí giao hàng</strong><br><small>Cho đơn từ @money(config('shop.shipping.free_min_order'))</small></div></div>
    <div class="gg-usp-item"><i class="fa fa-rotate-left"></i>
        <div><strong>Đổi trả {{ config('shop.return_window_days') }} ngày</strong><br><small>Kể từ khi nhận hàng</small></div></div>
    <div class="gg-usp-item"><i class="fa fa-hand-holding-dollar"></i>
        <div><strong>Thanh toán khi nhận hàng</strong><br><small>Nhận cây, kiểm tra rồi mới trả tiền</small></div></div>
</div>

{{-- Sản phẩm hot: bán chạy nhất trong 30 ngày (HomeController::hotPlants) --}}
@if($hotPlants->isNotEmpty())
    <div class="gg-section-head" id="san-pham-hot">
        <div><span class="gg-eyebrow">Bán chạy</span><h2><i class="fa fa-fire text-danger"></i> Sản phẩm hot</h2>
            <p>Được mua nhiều nhất {{ config('shop.home.hot_days') }} ngày qua</p></div>
        <a href="{{ route('plants.index') }}">Xem tất cả <i class="fa fa-arrow-right"></i></a>
    </div>
    <div class="row g-3 mb-2" data-testid="hot-plants">
        @foreach($hotPlants as $plant)
            <div class="col-6 col-md-4 col-lg-3">@include('partials.plant-card', ['plant' => $plant, 'sold' => $hotSold[$plant->id] ?? 0])</div>
        @endforeach
    </div>
@endif

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
<script src="{{ asset('js/slider.js') }}?v={{ @filemtime(public_path('js/slider.js')) }}"></script>
@endsection

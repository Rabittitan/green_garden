@extends('layouts.app')
@section('title', 'GreenGarden Shop - Trang chủ')

@section('content')
{{-- Banner --}}
<div class="hero text-white text-center rounded-3 py-5 mb-4">
    <div class="py-4">
        <h1 class="fw-bold">Cây Cảnh Phong Thủy & Nội Thất</h1>
        <p class="lead">Chọn cây hợp mệnh - Đón tài lộc, thanh lọc không khí</p>
        <a href="{{ route('plants.index') }}" class="btn btn-light btn-lg text-green fw-bold">Khám phá ngay</a>
    </div>
</div>

{{-- Chọn cây theo mệnh --}}
<h4 class="text-green fw-bold mb-3"><i class="fa fa-yin-yang"></i> Chọn cây theo mệnh của bạn</h4>
<div class="row g-2 mb-4 text-center">
    @foreach(\App\Models\Plant::ELEMENTS as $key => $label)
        <div class="col">
            <a href="{{ route('plants.index', ['element' => $key]) }}" class="btn btn-outline-green w-100 py-3 fw-bold">{{ $label }}</a>
        </div>
    @endforeach
</div>

{{-- Danh mục --}}
<h4 class="text-green fw-bold mb-3"><i class="fa fa-layer-group"></i> Danh mục</h4>
<div class="row g-3 mb-4">
    @foreach($categories as $cat)
        <div class="col-6 col-md">
            <a href="{{ route('plants.index', ['category' => $cat->id]) }}" class="text-decoration-none">
                <div class="card text-center h-100 border-success-subtle">
                    <div class="card-body">
                        <i class="fa-solid fa-leaf fa-2x text-green mb-2"></i>
                        <div class="fw-semibold text-dark">{{ $cat->name }}</div>
                        <small class="text-muted">{{ $cat->plants_count }} cây</small>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

{{-- Cây mới --}}
<h4 class="text-green fw-bold mb-3"><i class="fa fa-star"></i> Cây mới về</h4>
<div class="row g-3">
    @foreach($newPlants as $plant)
        <div class="col-6 col-md-3">@include('partials.plant-card', ['plant' => $plant])</div>
    @endforeach
</div>
<div class="text-center mt-4">
    <a href="{{ route('plants.index') }}" class="btn btn-outline-green">Xem tất cả cây cảnh <i class="fa fa-arrow-right"></i></a>
</div>

{{-- Bí Quyết Chăm Cây: 3 bài mới nhất --}}
<div class="d-flex justify-content-between align-items-end mt-5 mb-3">
    <h4 class="text-green fw-bold mb-0"><i class="fa fa-book-open"></i> Bí Quyết Chăm Cây</h4>
    <a href="{{ route('news.index') }}" class="text-green text-decoration-none">Xem tất cả <i class="fa fa-arrow-right"></i></a>
</div>
<div class="row g-3">
    @foreach($articles as $article)
        <div class="col-md-4">@include('partials.article-card', ['article' => $article])</div>
    @endforeach
</div>

@endsection
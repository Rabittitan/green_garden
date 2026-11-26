@extends('layouts.app')
@section('title', 'Mã của tôi')

@section('content')
@include('account._nav')
<div class="gg-page-head"><h1><i class="fa fa-ticket text-green"></i> Mã của tôi</h1></div>
<p class="text-muted">Mua cây đủ điều kiện, shop sẽ tặng mã vào đây (sau khi đơn qua {{ config('shop.return_window_days') }} ngày đổi trả).
    Mã tặng chỉ dùng được với tài khoản của bạn. Khi đặt hàng, bấm chọn mã ngay ở khung "Mã giảm giá".</p>

<h2 class="h5 fw-bold mt-4">Còn dùng được <span class="badge bg-success">{{ $usable->count() }}</span></h2>
<div class="row g-3" data-testid="wallet-usable">
    @forelse($usable as $c)
        <div class="col-md-6 col-lg-4">
            <div class="gg-coupon">
                <div class="gg-coupon-left"><i class="fa fa-gift"></i></div>
                <div class="gg-coupon-body">
                    <div class="fw-bold">{{ $c->benefitText() }}</div>
                    <div class="small text-muted">{{ $c->name }}</div>
                    @if($c->min_order_amount > 0)<div class="small">Đơn từ @money($c->min_order_amount)</div>@endif
                    <div class="small">Hạn đến <strong>{{ \App\Support\Format::datetime($c->ends_at, 'd/m/Y') }}</strong></div>
                    <div class="gg-coupon-code mt-1">{{ $c->code }}</div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="text-center bg-white border rounded-4 py-4 px-3 text-muted">Bạn chưa có mã riêng nào còn hạn.</div></div>
    @endforelse
</div>

@if($public->isNotEmpty())
    <h2 class="h5 fw-bold mt-4">Mã đang áp dụng cho mọi khách</h2>
    <div class="row g-3" data-testid="wallet-public">
        @foreach($public as $c)
            <div class="col-md-6 col-lg-4">
                <div class="gg-coupon gg-coupon-public">
                    <div class="gg-coupon-left"><i class="fa fa-ticket"></i></div>
                    <div class="gg-coupon-body">
                        <div class="fw-bold">{{ $c->benefitText() }}</div>
                        <div class="small text-muted">{{ $c->name }}</div>
                        <div class="small">
                            @if($c->min_order_amount > 0)Đơn từ @money($c->min_order_amount) · @endif
                            @if($c->minTier)Hạng {{ $c->minTier->name }} trở lên · @endif
                            @if($c->first_order_only)Đơn đầu tiên · @endif
                            {{ $c->ends_at ? 'Hạn đến '.\App\Support\Format::datetime($c->ends_at, 'd/m/Y') : 'Không thời hạn' }}
                        </div>
                        <div class="gg-coupon-code mt-1">{{ $c->code }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($history->isNotEmpty())
    <h2 class="h5 fw-bold mt-4">Đã dùng / hết hạn</h2>
    <ul class="list-group" data-testid="wallet-history">
        @foreach($history as $c)
            <li class="list-group-item d-flex justify-content-between align-items-center text-muted">
                <span><span class="text-decoration-line-through">{{ $c->code }}</span> · {{ $c->benefitText() }}</span>
                <span class="badge bg-secondary">{{ $c->used_count >= ($c->usage_limit ?? PHP_INT_MAX) ? 'Đã dùng' : 'Hết hạn' }}</span>
            </li>
        @endforeach
    </ul>
@endif
@endsection

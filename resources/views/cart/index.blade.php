@extends('layouts.app')
@section('title', 'Giỏ hàng')

@section('content')
<div class="gg-page-head">
    <span class="gg-eyebrow">Mua sắm</span>
    <h1><i class="fa fa-cart-shopping text-green"></i> Giỏ hàng của bạn</h1>
</div>

@if($lines->isEmpty())
    <div class="text-center bg-white border rounded-4 py-5 px-3">
        <div class="gg-auth-icon mx-auto"><i class="fa fa-cart-shopping"></i></div>
        <h2 class="h5 fw-bold">Giỏ hàng đang trống.</h2>
        <p class="text-muted">Hãy chọn vài chậu cây xanh cho không gian của bạn.</p>
        <a href="{{ route('plants.index') }}" class="btn btn-green">Mua sắm ngay</a>
    </div>
@else
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="gg-panel">
                @foreach($lines as $id => $line)
                    <div class="gg-cart-line">
                        <img src="{{ $line['plant']->image }}" alt="{{ $line['plant']->name }}"
                             onerror="this.onerror=null;this.src='https://placehold.co/76x76/e8f5e9/2e7d32?text=GG'">
                        <div>
                            <a href="{{ route('plants.show', $id) }}" class="fw-bold text-decoration-none text-dark">{{ $line['plant']->name }}</a>
                            <div class="small text-muted">
                                @if($line['unit'] < $line['plant']->price)
                                    <span class="badge bg-danger"><i class="fa fa-bolt"></i> Flash sale</span>
                                    <strong class="text-danger">@money($line['unit'])</strong> <s>@money($line['plant']->price)</s>
                                @else
                                    @money($line['plant']->price)
                                    @if($line['flash'])<span class="text-danger">(flash sale chỉ còn {{ $line['flash']->remaining() }} suất)</span>@endif
                                @endif
                                · Còn {{ $line['plant']->stock }} cây</div>
                        </div>
                        <div class="gg-cart-actions d-flex align-items-center gap-3">
                            <form action="{{ route('cart.update', $id) }}" method="POST" class="gg-stepper">
                                @csrf
                                <button name="action" value="decrease" aria-label="Giảm">-</button>
                                <strong>{{ $line['quantity'] }}</strong>
                                <button name="action" value="increase" aria-label="Tăng" @disabled($line['quantity'] >= $line['plant']->stock)>+</button>
                            </form>
                            <div class="fw-bold text-end" style="min-width: 110px">@money($line['total'])</div>
                            <form action="{{ route('cart.remove', $id) }}" method="POST">
                                @csrf
                                <button class="btn btn-sm btn-link text-danger" title="Xóa"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('plants.index') }}" class="btn btn-link text-green mt-2 px-0">← Tiếp tục mua</a>
        </div>
        <div class="col-lg-4">
            <div class="gg-panel gg-panel-body gg-sticky">
                <h2 class="h6 fw-bold mb-3">Tóm tắt</h2>
                <div class="gg-summary-row"><span>Tạm tính</span><strong class="text-green">@money($subtotal)</strong></div>
                <div class="small text-muted mt-2">Ưu đãi hạng thành viên, mã giảm giá và phí ship được tính ở bước đặt hàng.</div>
                <a href="{{ route('checkout.form') }}" class="btn btn-green btn-lg w-100 mt-3">Tiến hành đặt hàng</a>
            </div>
        </div>
    </div>
@endif
@endsection

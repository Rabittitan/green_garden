@extends('layouts.app')
@section('title', 'Đơn hàng của tôi')

@section('content')
@include('account._nav')
<div class="gg-page-head">
    <h1><i class="fa fa-clock-rotate-left text-green"></i> Đơn hàng của tôi</h1>
</div>

@if($orders->isEmpty())
    <div class="text-center bg-white border rounded-4 py-5 px-3">
        <div class="gg-auth-icon mx-auto"><i class="fa fa-receipt"></i></div>
        <h2 class="h5 fw-bold">Bạn chưa có đơn hàng nào.</h2>
        <a href="{{ route('plants.index') }}" class="btn btn-green mt-2">Bắt đầu mua sắm</a>
    </div>
@else
    <div class="gg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr class="small text-muted"><th class="ps-3">Mã đơn</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th><th></th></tr></thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td class="fw-bold ps-3">{{ $order->code }}</td>
                            <td>@vndt($order->placed_at)</td>
                            <td class="fw-bold">@money($order->total)</td>
                            <td><span class="badge rounded-pill bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span></td>
                            <td><span class="badge rounded-pill bg-{{ $order->payment_status->color() }}">{{ $order->payment_status->label() }}</span></td>
                            <td class="text-end pe-3"><a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-outline-green">Xem</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
@endif
@endsection

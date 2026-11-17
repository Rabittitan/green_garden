@extends('layouts.app')
@section('title', 'Đơn hàng của tôi')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-clock-rotate-left"></i> Đơn hàng của tôi</h4>

@if($orders->isEmpty())
    <div class="alert alert-info">Bạn chưa có đơn hàng nào.</div>
@else
    <div class="table-responsive">
    <table class="table bg-white shadow-sm align-middle">
        <thead class="table-success"><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th><th></th></tr></thead>
        <tbody>
            @foreach($orders as $order)
                <tr>
                    <td class="fw-bold">{{ $order->code }}</td>
                    <td>@vndt($order->placed_at)</td>
                    <td class="fw-bold">@money($order->total)</td>
                    <td><span class="badge bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span></td>
                    <td><span class="badge bg-{{ $order->payment_status->color() }}">{{ $order->payment_status->label() }}</span></td>
                    <td><a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-outline-green">Xem</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    {{ $orders->links() }}
@endif
@endsection

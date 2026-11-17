@extends('layouts.app')
@section('title', 'Chi tiết đơn #' . $order->id)

@section('content')
@php $color = ['pending'=>'warning','shipping'=>'info','completed'=>'success','cancelled'=>'danger'][$order->status]; @endphp

<a href="{{ route('orders.history') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Lịch sử đơn hàng</a>
<h4 class="fw-bold mt-2">Đơn hàng #{{ $order->id }}
    <span class="badge bg-{{ $color }} fs-6">{{ \App\Models\Order::STATUSES[$order->status] }}</span>
</h4>

<div class="row g-4 mt-1">
    <div class="col-md-4">
        <div class="card card-body shadow-sm">
            <h6 class="fw-bold">Thông tin giao hàng</h6>
            <div>{{ $order->customer_name }}</div>
            <div>{{ $order->phone }}</div>
            <div>{{ $order->address }}</div>
            @if($order->note) <div class="text-muted mt-2">Ghi chú: {{ $order->note }}</div> @endif
            <div class="text-muted mt-2">Ngày đặt: {{ $order->created_at->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    <div class="col-md-8">
        <table class="table bg-white shadow-sm align-middle">
            <thead class="table-success"><tr><th>Cây</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->plant->name }}</td>
                        <td>{{ number_format($item->price, 0, ',', '.') }}đ</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->price * $item->quantity, 0, ',', '.') }}đ</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><th colspan="3" class="text-end">Tổng cộng:</th><th class="text-green fs-5">{{ number_format($order->total, 0, ',', '.') }}đ</th></tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
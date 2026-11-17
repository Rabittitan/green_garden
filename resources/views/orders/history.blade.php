@extends('layouts.app')
@section('title', 'Đơn hàng của tôi')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-clock-rotate-left"></i> Lịch sử đơn hàng</h4>

@if($orders->isEmpty())
    <div class="alert alert-info">Bạn chưa có đơn hàng nào.</div>
@else
    <table class="table bg-white shadow-sm align-middle">
        <thead class="table-success"><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
            @foreach($orders as $order)
                @php $color = ['pending'=>'warning','shipping'=>'info','completed'=>'success','cancelled'=>'danger'][$order->status]; @endphp
                <tr>
                    <td>#{{ $order->id }}</td>
                    <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    <td class="fw-bold">{{ number_format($order->total, 0, ',', '.') }}đ</td>
                    <td><span class="badge bg-{{ $color }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span></td>
                    <td><a href="{{ route('orders.show', $order->id) }}" class="btn btn-sm btn-outline-green">Xem</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $orders->links() }}
@endif
@endsection
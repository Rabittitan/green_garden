@extends('layouts.admin')
@section('title', 'Quản lý đơn hàng')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-receipt"></i> Quản lý đơn hàng</h4>

{{-- Nút lọc theo trạng thái --}}
<div class="mb-3">
    <a href="{{ route('admin.orders.index') }}" class="btn btn-sm {{ request('status') ? 'btn-outline-dark' : 'btn-dark' }}">Tất cả</a>
    @foreach(\App\Models\Order::STATUSES as $key => $label)
        <a href="{{ route('admin.orders.index', ['status' => $key]) }}"
           class="btn btn-sm {{ request('status') == $key ? 'btn-success' : 'btn-outline-success' }}">{{ $label }}</a>
    @endforeach
</div>

<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success">
        <tr><th>Mã</th><th>Tài khoản</th><th>Người nhận</th><th>SĐT</th><th>Tổng tiền</th><th>Ngày đặt</th><th>Trạng thái</th><th></th></tr>
    </thead>
    <tbody>
        @forelse($orders as $order)
            @php $color = ['pending'=>'warning','shipping'=>'info','completed'=>'success','cancelled'=>'danger'][$order->status]; @endphp
            <tr>
                <td>#{{ $order->id }}</td>
                <td>{{ $order->user->name }}</td>
                <td>{{ $order->customer_name }}</td>
                <td>{{ $order->phone }}</td>
                <td class="fw-bold">{{ number_format($order->total, 0, ',', '.') }}đ</td>
                <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                <td><span class="badge bg-{{ $color }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span></td>
                <td><a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-sm btn-outline-success">Chi tiết</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Không có đơn hàng nào.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $orders->links() }}
@endsection
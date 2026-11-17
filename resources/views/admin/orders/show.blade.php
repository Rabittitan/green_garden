@extends('layouts.admin')
@section('title', 'Đơn #' . $order->id)

@section('content')
@php $color = ['pending'=>'warning','shipping'=>'info','completed'=>'success','cancelled'=>'danger'][$order->status]; @endphp

<a href="{{ route('admin.orders.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Danh sách đơn</a>
<h4 class="fw-bold mt-2">Đơn hàng #{{ $order->id }}
    <span class="badge bg-{{ $color }} fs-6">{{ \App\Models\Order::STATUSES[$order->status] }}</span>
</h4>

<div class="row g-4 mt-1">
    <div class="col-md-4">
        <div class="card card-body shadow-sm mb-3">
            <h6 class="fw-bold">Khách hàng</h6>
            <div>Tài khoản: {{ $order->user->name }} ({{ $order->user->email }})</div>
            <div>Người nhận: {{ $order->customer_name }}</div>
            <div>SĐT: {{ $order->phone }}</div>
            <div>Địa chỉ: {{ $order->address }}</div>
            @if($order->note) <div class="text-muted">Ghi chú: {{ $order->note }}</div> @endif
            <div class="text-muted mt-1">Ngày đặt: {{ $order->created_at->format('d/m/Y H:i') }}</div>
        </div>

        {{-- Chỉ hiện nút khi đơn chưa hoàn thành/hủy --}}
        <div class="card card-body shadow-sm">
            <h6 class="fw-bold">Cập nhật trạng thái</h6>
            @if(in_array($order->status, ['pending', 'shipping']))
                <form action="{{ route('admin.orders.status', $order->id) }}" method="POST" class="d-grid gap-2">
                    @csrf
                    @if($order->status === 'pending')
                        <button name="status" value="shipping" class="btn btn-info">Chuyển sang "Đang giao"</button>
                    @endif
                    <button name="status" value="completed" class="btn btn-success">Đánh dấu "Hoàn thành"</button>
                    <button name="status" value="cancelled" class="btn btn-danger"
                            onclick="return confirm('Hủy đơn này? Số cây sẽ được cộng trả lại kho.')">Hủy đơn (hoàn kho)</button>
                </form>
            @else
                <div class="text-muted">Đơn đã {{ strtolower(\App\Models\Order::STATUSES[$order->status]) }}, không thể thay đổi.</div>
            @endif
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
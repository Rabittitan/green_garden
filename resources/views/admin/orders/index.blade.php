@extends('layouts.admin')
@section('title', 'Đơn hàng')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-receipt"></i> Đơn hàng</h4>

<div class="mb-2 d-flex flex-wrap gap-1">
    <a href="{{ route('admin.orders.index') }}" class="btn btn-sm {{ request()->hasAny(['status', 'payment_status']) ? 'btn-outline-dark' : 'btn-dark' }}">Tất cả</a>
    @foreach(\App\Enums\OrderStatus::cases() as $s)
        <a href="{{ route('admin.orders.index', ['status' => $s->value]) }}"
           class="btn btn-sm {{ request('status') === $s->value ? 'btn-success' : 'btn-outline-success' }}">{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</a>
    @endforeach
    <a href="{{ route('admin.orders.index', ['payment_status' => 'refund_pending']) }}"
       class="btn btn-sm {{ request('payment_status') === 'refund_pending' ? 'btn-warning' : 'btn-outline-warning' }}">Chờ hoàn tiền</a>
</div>
<form class="mb-3 d-flex flex-wrap gap-2" method="GET">
    @foreach(request()->only(['status', 'payment_status']) as $k => $v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endforeach
    <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:300px" placeholder="Mã đơn / SĐT / tên người nhận">
    <button class="btn btn-sm btn-outline-secondary">Tìm</button>
</form>

{{-- Điện thoại: mỗi đơn hiện thành 1 thẻ (gg-table-stack), không phải cuộn ngang --}}
<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle gg-table-stack">
    <thead class="table-success">
        <tr><th>Mã</th><th>Khách</th><th>Người nhận</th><th>Tổng tiền</th><th>Thanh toán</th><th>Ngày đặt</th><th>Trạng thái</th><th></th></tr>
    </thead>
    <tbody>
        @forelse($orders as $order)
            <tr>
                <td class="fw-bold" data-label="Mã">{{ $order->code }}</td>
                <td data-label="Khách">{{ $order->user->name }}</td>
                <td data-label="Người nhận">{{ $order->recipient_name }}<div class="small text-muted">{{ $order->phone }}</div></td>
                <td class="fw-bold" data-label="Tổng tiền">@money($order->total)</td>
                <td data-label="Thanh toán"><span class="badge bg-{{ $order->payment_status->color() }}">{{ $order->payment_status->label() }}</span>
                    <div class="small text-muted">{{ $order->payment_method->label() }}</div></td>
                <td data-label="Ngày đặt">@vndt($order->placed_at)</td>
                <td data-label="Trạng thái"><span class="badge bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span></td>
                <td data-label=""><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-success text-nowrap">Chi tiết</a></td>
            </tr>
        @empty
            <tr><td colspan="8" data-label="" class="text-center text-muted py-4 gg-stack-wide">Không có đơn hàng nào.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
{{ $orders->links() }}
@endsection

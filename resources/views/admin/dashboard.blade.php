@extends('layouts.admin')
@section('title', 'Thống kê')

@section('content')
<h4 class="text-green fw-bold mb-4"><i class="fa fa-chart-line"></i> Thống kê</h4>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card text-white bg-success h-100"><div class="card-body">
        <div>Doanh thu hàng hóa</div><div class="fs-4 fw-bold">@money($revenue)</div>
        <small>đơn hoàn thành, sau giảm giá, không gồm ship</small>
    </div></div></div>
    <div class="col-md-3"><div class="card text-white bg-primary h-100"><div class="card-body">
        <div>Lợi nhuận gộp</div><div class="fs-4 fw-bold">@money($revenue - $cogs)</div>
        <small>doanh thu − giá vốn lúc bán (@money($cogs))</small>
    </div></div></div>
    <div class="col-md-3"><div class="card text-white bg-secondary h-100"><div class="card-body">
        <div>Tiền thực thu</div><div class="fs-4 fw-bold">@money($cashIn)</div>
        <small>đã thu − đã hoàn (@money($refunded))</small>
    </div></div></div>
    <div class="col-md-3"><div class="card text-dark bg-warning h-100"><div class="card-body">
        <div>Việc cần làm</div>
        <div><a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="text-dark">{{ $counts['pending'] ?? 0 }} đơn chờ xác nhận</a></div>
        <div><a href="{{ route('admin.orders.index', ['status' => 'confirmed']) }}" class="text-dark">{{ $counts['confirmed'] ?? 0 }} đơn chờ giao</a></div>
        <div><a href="{{ route('admin.orders.index', ['status' => 'returning']) }}" class="text-dark">{{ $counts['returning'] ?? 0 }} đơn đang hoàn về</a></div>
        <div><a href="{{ route('admin.orders.index', ['payment_status' => 'refund_pending']) }}" class="text-dark fw-bold">{{ $needRefund }} đơn chờ hoàn tiền</a></div>
    </div></div></div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="fa fa-fire text-danger"></i> Top 5 cây bán chạy</div>
            <table class="table mb-0 align-middle">
                <thead><tr><th>#</th><th>Cây</th><th class="text-end">Đã bán</th></tr></thead>
                <tbody>
                    @forelse($topPlants as $i => $row)
                        <tr><td>{{ $i + 1 }}</td><td>{{ $row->plant_name }}</td><td class="text-end fw-bold">{{ $row->sold }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">Chưa có đơn hoàn thành nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between">
                <span><i class="fa fa-triangle-exclamation text-warning"></i> Cây dưới ngưỡng tồn</span>
                <a href="{{ route('admin.inventory.index', ['low' => 1]) }}" class="small">Xem kho</a>
            </div>
            <table class="table mb-0">
                <thead><tr><th>Cây</th><th class="text-end">Tồn / Ngưỡng</th></tr></thead>
                <tbody>
                    @forelse($lowStock as $p)
                        <tr><td><a href="{{ route('admin.inventory.show', $p) }}" class="text-decoration-none">{{ $p->name }}</a></td>
                            <td class="text-end"><span class="badge bg-danger">{{ $p->stock }}</span> / {{ $p->reorder_point }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">Kho còn đủ hàng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="fa fa-receipt"></i> Đơn mới nhất</div>
            <table class="table mb-0 align-middle">
                <thead><tr><th>Mã</th><th>Người nhận</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th><th></th></tr></thead>
                <tbody>
                    @foreach($latestOrders as $order)
                        <tr>
                            <td>{{ $order->code }}</td><td>{{ $order->recipient_name }}</td><td>@money($order->total)</td>
                            <td><span class="badge bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span></td>
                            <td><span class="badge bg-{{ $order->payment_status->color() }}">{{ $order->payment_status->label() }}</span></td>
                            <td><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-success">Xem</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

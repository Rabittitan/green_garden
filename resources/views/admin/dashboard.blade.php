@extends('layouts.admin')
@section('title', 'Thống kê')

@section('content')
<h4 class="text-green fw-bold mb-4"><i class="fa fa-chart-line"></i> Thống kê doanh thu</h4>

{{-- 4 ô số liệu --}}
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card text-white bg-success h-100"><div class="card-body">
        <div>Tổng doanh thu</div>
        <div class="fs-4 fw-bold">{{ number_format($totalRevenue, 0, ',', '.') }}đ</div>
        <small>(chỉ tính đơn đã hoàn thành)</small>
    </div></div></div>
    <div class="col-md-3"><div class="card text-white bg-primary h-100"><div class="card-body">
        <div>Tổng đơn hàng</div><div class="fs-4 fw-bold">{{ $totalOrders }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-dark bg-warning h-100"><div class="card-body">
        <div>Đơn chờ xử lý</div><div class="fs-4 fw-bold">{{ $pendingOrders }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card text-white bg-secondary h-100"><div class="card-body">
        <div>Số loại cây</div><div class="fs-4 fw-bold">{{ $totalPlants }}</div>
    </div></div></div>
</div>

<div class="row g-4">
    {{-- Top bán chạy --}}
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="fa fa-fire text-danger"></i> Top 5 cây bán chạy</div>
            <table class="table mb-0 align-middle">
                <thead><tr><th>#</th><th>Cây</th><th class="text-end">Đã bán</th></tr></thead>
                <tbody>
                    @forelse($topPlants as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row->plant->name }}</td>
                            <td class="text-end fw-bold">{{ $row->sold }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">Chưa có đơn hoàn thành nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Sắp hết hàng --}}
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="fa fa-triangle-exclamation text-warning"></i> Cây sắp hết hàng (≤ 5)</div>
            <table class="table mb-0">
                <thead><tr><th>Cây</th><th class="text-end">Tồn kho</th></tr></thead>
                <tbody>
                    @forelse($lowStock as $p)
                        <tr>
                            <td><a href="{{ route('admin.plants.edit', $p->id) }}" class="text-decoration-none">{{ $p->name }}</a></td>
                            <td class="text-end"><span class="badge bg-danger">{{ $p->stock }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">Kho còn đủ hàng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Đơn mới nhất --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="fa fa-receipt"></i> 5 đơn mới nhất</div>
            <table class="table mb-0 align-middle">
                <thead><tr><th>Mã</th><th>Người nhận</th><th>Tổng tiền</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody>
                    @foreach($latestOrders as $order)
                        @php $color = ['pending'=>'warning','shipping'=>'info','completed'=>'success','cancelled'=>'danger'][$order->status]; @endphp
                        <tr>
                            <td>#{{ $order->id }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td>{{ number_format($order->total, 0, ',', '.') }}đ</td>
                            <td><span class="badge bg-{{ $color }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span></td>
                            <td><a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-sm btn-outline-success">Xem</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
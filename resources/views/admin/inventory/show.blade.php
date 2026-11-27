@extends('layouts.admin')
@section('title', 'Sổ kho - ' . $plant->name)

@section('content')
<a href="{{ route('admin.inventory.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Kho</a>
<h4 class="fw-bold mt-2">{{ $plant->name }} <small class="text-muted">{{ $plant->sku }}</small></h4>
<p>Tồn hiện tại: <strong class="fs-5">{{ $plant->stock }}</strong> · Ngưỡng nhập thêm: {{ $plant->reorder_point }} · Giá vốn bình quân: @money($plant->cost_price)</p>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card card-body shadow-sm h-100" data-testid="units-card">
            <h6 class="fw-bold"><i class="fa fa-qrcode"></i> Từng chậu (mã riêng)</h6>
            <p class="small text-muted mb-2">Trên kệ <strong>{{ $unitsOnShelf }}</strong> chậu
                ({{ $plant->stock }} còn bán được{{ $unitsOnShelf > $plant->stock ? ' + '.($unitsOnShelf - $plant->stock).' đã có đơn đặt, chờ quét giao' : '' }}).</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.units.index', ['plant' => $plant->id]) }}" class="btn btn-sm btn-outline-green"><i class="fa fa-list"></i> Xem các chậu</a>
                <a href="{{ route('admin.units.labels', ['plant' => $plant->id]) }}" class="btn btn-sm btn-green"><i class="fa fa-print"></i> In tem chậu trong kho</a>
            </div>
        </div>
    </div>
    @if(auth()->user()->isAdmin())
    <div class="col-md-6">
        <div class="card card-body shadow-sm h-100">
            <h6 class="fw-bold">Kiểm kê</h6>
            <p class="small text-muted mb-2">Kiểm kê làm bằng <strong>phiếu kiểm kê</strong> (chốt số trên sổ, nhập số đếm, xem chênh lệch rồi mới điều chỉnh kho).</p>
            <a href="{{ route('admin.stock-counts.index') }}" class="btn btn-outline-green align-self-start"><i class="fa fa-clipboard-check"></i> Phiếu kiểm kê</a>
        </div>
    </div>
    @endif
    <div class="col-md-6">
        <div class="card card-body shadow-sm h-100">
            <h6 class="fw-bold">Hàng lỗi / cây chết</h6>
            <p class="small text-muted mb-2">Lập <strong>phiếu báo lỗi</strong>: quét tem từng chậu lỗi, chọn loại lỗi, chụp ảnh. Quản trị viên duyệt thì chậu mới được xuất hủy khỏi kho.</p>
            <a href="{{ route('admin.defect-reports.create') }}" class="btn btn-outline-danger align-self-start"><i class="fa fa-triangle-exclamation"></i> Lập phiếu báo lỗi</a>
        </div>
    </div>
</div>

<table class="table bg-white shadow-sm align-middle small">
    <thead class="table-success"><tr><th>Thời gian</th><th>Loại</th><th class="text-end">SL</th><th class="text-end">Tồn sau</th><th class="text-end">Giá vốn</th><th>Chứng từ</th><th>Người thực hiện / ghi chú</th></tr></thead>
    <tbody>
        @foreach($movements as $m)
            <tr>
                <td>@vndt($m->created_at)</td>
                <td>{{ $m->type->label() }}</td>
                <td class="text-end fw-bold {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                <td class="text-end">{{ $m->stock_after }}</td>
                <td class="text-end">@money($m->unit_cost)</td>
                <td>
                    @if($m->orderItem) <a href="{{ route('admin.orders.show', $m->orderItem->order) }}">{{ $m->orderItem->order->code }}</a>
                    @elseif($m->goodsReceiptItem) <a href="{{ route('admin.goods-receipts.show', $m->goodsReceiptItem->receipt) }}">{{ $m->goodsReceiptItem->receipt->code }}</a>
                    @elseif($m->defectItem) <a href="{{ route('admin.defect-reports.show', $m->defectItem->defect_report_id) }}">Phiếu báo lỗi</a> @endif
                </td>
                <td>{{ $m->creator?->name }} {{ $m->note ? '· '.$m->note : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $movements->links() }}
@endsection

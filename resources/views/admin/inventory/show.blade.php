@extends('layouts.admin')
@section('title', 'Sổ kho - ' . $plant->name)

@section('content')
<a href="{{ route('admin.inventory.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Kho</a>
<h4 class="fw-bold mt-2">{{ $plant->name }} <small class="text-muted">{{ $plant->sku }}</small></h4>
<p>Tồn hiện tại: <strong class="fs-5">{{ $plant->stock }}</strong> · Ngưỡng nhập thêm: {{ $plant->reorder_point }} · Giá vốn bình quân: @money($plant->cost_price)</p>

<div class="row g-3 mb-4">
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
        <form action="{{ route('admin.inventory.damage', $plant) }}" method="POST" class="card card-body shadow-sm">@csrf
            <h6 class="fw-bold">Ghi hư hỏng / cây chết</h6>
            <p class="small text-muted mb-2">Xuất khỏi kho số cây không còn bán được.</p>
            <div class="input-group">
                <input type="number" name="quantity" min="1" max="{{ $plant->stock }}" class="form-control" placeholder="Số lượng" required>
                <input name="reason" class="form-control" placeholder="Lý do" required>
                <button class="btn btn-outline-danger">Ghi nhận</button>
            </div>
        </form>
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
                    @elseif($m->goodsReceiptItem) <a href="{{ route('admin.goods-receipts.show', $m->goodsReceiptItem->receipt) }}">{{ $m->goodsReceiptItem->receipt->code }}</a> @endif
                </td>
                <td>{{ $m->creator?->name }} {{ $m->note ? '· '.$m->note : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $movements->links() }}
@endsection

@extends('layouts.admin')
@section('title', 'Phiếu nhập ' . $receipt->code)

@section('content')
<a href="{{ route('admin.goods-receipts.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Phiếu nhập kho</a>
<h4 class="fw-bold mt-2">Phiếu {{ $receipt->code }} <span class="badge bg-{{ $receipt->status->color() }} fs-6">{{ $receipt->status->label() }}</span></h4>
<p class="text-muted">NCC: <strong>{{ $receipt->supplier->name }}</strong>
    @if($receipt->purchaseRequest) · Theo yêu cầu <a href="{{ route('admin.purchase-requests.show', $receipt->purchaseRequest) }}">{{ $receipt->purchaseRequest->code }}</a> @endif
    @if($receipt->supplier_invoice_no) · HĐ {{ $receipt->supplier_invoice_no }} @endif
    · Lập bởi {{ $receipt->receiver->name }} @vndt($receipt->created_at)
    @if($receipt->received_at) · Nhập kho @vndt($receipt->received_at) @endif</p>
@if($receipt->note)<p>Ghi chú: {{ $receipt->note }}</p>@endif

<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success"><tr><th>Cây</th><th class="text-end">Đạt (nhập kho)</th><th class="text-end">Loại</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr></thead>
    <tbody>
        @foreach($receipt->items as $i)
            <tr><td>{{ $i->plant->name }}</td><td class="text-end">{{ $i->quantity_received }}</td><td class="text-end">{{ $i->quantity_rejected }}</td>
                <td class="text-end">@money($i->unit_cost)</td><td class="text-end">@money($i->quantity_received * $i->unit_cost)</td></tr>
        @endforeach
    </tbody>
    <tfoot><tr><th colspan="4" class="text-end">Tổng</th><th class="text-end">@money($receipt->total_cost)</th></tr></tfoot>
</table>

@if($receipt->status === \App\Enums\GoodsReceiptStatus::Draft)
    <div class="d-flex gap-2">
        <form action="{{ route('admin.goods-receipts.complete', $receipt) }}" method="POST" onsubmit="return confirm('Nhập kho phiếu {{ $receipt->code }}? Tồn kho và giá vốn sẽ được cập nhật, không thể hoàn tác.')">@csrf
            <button class="btn btn-green"><i class="fa fa-box-archive"></i> Nhập kho</button></form>
        <form action="{{ route('admin.goods-receipts.cancel', $receipt) }}" method="POST" onsubmit="return confirm('Hủy phiếu nháp?')">@csrf
            <button class="btn btn-outline-danger">Hủy phiếu</button></form>
    </div>
    <p class="small text-muted mt-2 mb-0">Khi nhập kho, mỗi chậu được phát một mã riêng; sau đó in tem QR ở đây và dán lên từng chậu.</p>
@elseif($receipt->status === \App\Enums\GoodsReceiptStatus::Completed)
    <div class="gg-panel gg-panel-body d-flex flex-wrap align-items-center gap-3" data-testid="receipt-labels">
        <div class="flex-grow-1"><strong>Tem QR cho chậu của phiếu này</strong>
            <div class="small text-muted">Mỗi chậu nhập về có một mã riêng. In tem (máy in tem nhiệt, khổ 50 × 30 mm) rồi dán lên từng chậu trước khi đưa lên kệ.</div></div>
        <a href="{{ route('admin.units.labels', ['receipt' => $receipt->id]) }}" class="btn btn-green"><i class="fa fa-print"></i> In tem</a>
    </div>
@endif
@endsection

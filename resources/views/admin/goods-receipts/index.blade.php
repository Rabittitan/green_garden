@extends('layouts.admin')
@section('title', 'Phiếu nhập kho')

@section('content')
<div class="d-flex justify-content-between mb-3">
    <h4 class="text-green fw-bold"><i class="fa fa-truck-ramp-box"></i> Phiếu nhập kho</h4>
    <a href="{{ route('admin.goods-receipts.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Phiếu nhập lẻ (không theo yêu cầu)</a>
</div>
<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success"><tr><th>Mã</th><th>Nhà cung cấp</th><th>Theo yêu cầu</th><th>HĐ NCC</th><th class="text-end">Tổng tiền</th><th>Người lập</th><th>Nhập kho lúc</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
        @forelse($receipts as $r)
            <tr>
                <td class="fw-bold">{{ $r->code }}</td>
                <td>{{ $r->supplier->name }}</td>
                <td>@if($r->purchaseRequest)<a href="{{ route('admin.purchase-requests.show', $r->purchaseRequest) }}">{{ $r->purchaseRequest->code }}</a>@else <span class="text-muted">Nhập lẻ</span> @endif</td>
                <td>{{ $r->supplier_invoice_no }}</td>
                <td class="text-end">@money($r->total_cost)</td>
                <td>{{ $r->receiver->name }}</td>
                <td>@vndt($r->received_at)</td>
                <td><span class="badge bg-{{ $r->status->color() }}">{{ $r->status->label() }}</span></td>
                <td><a href="{{ route('admin.goods-receipts.show', $r) }}" class="btn btn-sm btn-outline-success">Chi tiết</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">Chưa có phiếu nhập.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $receipts->links() }}
@endsection

@extends('layouts.admin')
@section('title', 'Yêu cầu nhập ' . $pr->code)

@section('content')
@use('App\Enums\PurchaseRequestStatus', 'PR')
@php
    $me = auth()->user();
    $isAdmin = $me->role === \App\Enums\UserRole::Admin;
    $canDecide = $isAdmin && $pr->status === PR::Submitted && $pr->requested_by !== $me->id;
@endphp
<a href="{{ route('admin.purchase-requests.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Yêu cầu nhập</a>
<h4 class="fw-bold mt-2">Yêu cầu {{ $pr->code }} <span class="badge bg-{{ $pr->status->color() }} fs-6">{{ $pr->status->label() }}</span></h4>
<p class="text-muted">Lập bởi {{ $pr->requester->name }} lúc @vndt($pr->created_at)
    @if($pr->approver) · Duyệt bởi {{ $pr->approver->name }} lúc @vndt($pr->approved_at) @endif
    @if($pr->supplier) · NCC: <strong>{{ $pr->supplier->name }}</strong> @endif
    @if($pr->needed_by) · Cần trước {{ $pr->needed_by->format('d/m/Y') }} @endif</p>
@if($pr->reason)<p>Lý do: {{ $pr->reason }}</p>@endif
@if($pr->rejected_reason)<div class="alert alert-danger">Ghi chú từ chối / hủy / đóng: {{ $pr->rejected_reason }}</div>@endif

<div class="row g-4">
    <div class="col-lg-8">
        <form action="{{ route('admin.purchase-requests.approve', $pr) }}" method="POST" id="approve-form">@csrf
        <table class="table bg-white shadow-sm align-middle">
            <thead class="table-success"><tr><th>Cây</th><th class="text-end">Tồn lúc lập</th><th class="text-end">Đề xuất</th><th class="text-end">Duyệt</th><th class="text-end">Đã nhận</th><th class="text-end">Còn lại</th><th class="text-end">Đơn giá DK</th></tr></thead>
            <tbody>
                @foreach($pr->items as $item)
                    <tr>
                        <td>{{ $item->plant->name }}@if($item->note)<div class="small text-muted">{{ $item->note }}</div>@endif</td>
                        <td class="text-end">{{ $item->stock_at_request }}</td>
                        <td class="text-end">{{ $item->quantity_requested }}</td>
                        <td class="text-end" style="width:120px">
                            @if($canDecide)
                                <input type="number" min="0" max="{{ $item->quantity_requested }}" name="approved[{{ $item->id }}]" value="{{ $item->quantity_requested }}" class="form-control form-control-sm text-end">
                            @else {{ $item->quantity_approved ?? '—' }} @endif
                        </td>
                        <td class="text-end">{{ $item->quantity_received }}</td>
                        <td class="text-end fw-bold">{{ $pr->status->isReceivable() ? $item->remaining : '' }}</td>
                        <td class="text-end">@money($item->estimated_unit_cost)</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><th colspan="6" class="text-end">Tổng dự kiến</th><th class="text-end">@money($pr->estimated_total)</th></tr></tfoot>
        </table>
        </form>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold d-flex justify-content-between">
                <span>Phiếu nhập của yêu cầu này</span>
                @if($pr->status->isReceivable())
                    <a href="{{ route('admin.goods-receipts.create', ['purchase_request' => $pr->id]) }}" class="btn btn-sm btn-green"><i class="fa fa-plus"></i> Lập phiếu nhập</a>
                @endif
            </div>
            <ul class="list-group list-group-flush">
                @forelse($pr->goodsReceipts as $gr)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="{{ route('admin.goods-receipts.show', $gr) }}">{{ $gr->code }}</a>
                        <span>@money($gr->total_cost) <span class="badge bg-{{ $gr->status->color() }}">{{ $gr->status->label() }}</span></span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Chưa có phiếu nhập.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-body shadow-sm">
            <h6 class="fw-bold">Xử lý</h6>
            @if($pr->status === PR::Draft)
                <a href="{{ route('admin.purchase-requests.edit', $pr) }}" class="btn btn-outline-secondary w-100 mb-2"><i class="fa fa-pen"></i> Sửa</a>
                <form action="{{ route('admin.purchase-requests.transition', $pr) }}" method="POST" class="mb-2">@csrf
                    <input type="hidden" name="to" value="submitted"><button class="btn btn-primary w-100">Gửi duyệt</button></form>
            @endif
            @if($canDecide)
                <button form="approve-form" class="btn btn-success w-100 mb-2"><i class="fa fa-check"></i> Duyệt (theo số ở cột "Duyệt")</button>
                <form action="{{ route('admin.purchase-requests.reject', $pr) }}" method="POST" class="mb-2">@csrf
                    <input name="reason" class="form-control mb-1" placeholder="Lý do từ chối" required>
                    <button class="btn btn-outline-danger w-100">Từ chối</button></form>
            @elseif($pr->status === PR::Submitted)
                <div class="alert alert-info small">Chờ quản trị viên khác người lập duyệt (nguyên tắc 4 mắt).</div>
            @endif
            @if($pr->status === PR::Submitted)
                <form action="{{ route('admin.purchase-requests.transition', $pr) }}" method="POST" class="mb-2">@csrf
                    <input type="hidden" name="to" value="draft"><button class="btn btn-outline-secondary w-100">Trả về nháp để sửa</button></form>
            @endif
            @if($pr->status === PR::Approved)
                <form action="{{ route('admin.purchase-requests.order', $pr) }}" method="POST" class="mb-2">@csrf
                    <select name="supplier_id" class="form-select mb-1" required>
                        @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected($s->id === $pr->supplier_id)>{{ $s->name }}</option>@endforeach
                    </select>
                    <button class="btn btn-info w-100">Đã đặt hàng với NCC</button></form>
            @endif
            @if($pr->status === PR::PartiallyReceived)
                <form action="{{ route('admin.purchase-requests.transition', $pr) }}" method="POST" class="mb-2">@csrf
                    <input type="hidden" name="to" value="received">
                    <input name="reason" class="form-control mb-1" placeholder="Lý do đóng (NCC không giao thêm)" required>
                    <button class="btn btn-outline-primary w-100">Đóng yêu cầu (chấp nhận nhận thiếu)</button></form>
            @endif
            @if($pr->status->canTransitionTo(PR::Cancelled))
                <form action="{{ route('admin.purchase-requests.transition', $pr) }}" method="POST" onsubmit="return confirm('Hủy yêu cầu {{ $pr->code }}?')">@csrf
                    <input type="hidden" name="to" value="cancelled">
                    <input name="reason" class="form-control mb-1" placeholder="Lý do hủy" required>
                    <button class="btn btn-outline-danger w-100">Hủy yêu cầu</button></form>
            @endif
            @if(in_array($pr->status, [PR::Received, PR::Rejected, PR::Cancelled], true))
                <div class="text-muted">Yêu cầu đã đóng.</div>
            @endif
        </div>
    </div>
</div>
@endsection

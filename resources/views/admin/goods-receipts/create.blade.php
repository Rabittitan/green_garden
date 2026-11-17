@extends('layouts.admin')
@section('title', 'Lập phiếu nhập')

@section('content')
<h4 class="text-green fw-bold mb-3">Lập phiếu nhập kho {{ $pr ? 'theo yêu cầu '.$pr->code : '(nhập lẻ)' }}</h4>
@if($suppliers->isEmpty())
    <div class="alert alert-warning">Chưa có nhà cung cấp nào. <a href="{{ route('admin.suppliers.index') }}">Thêm nhà cung cấp</a> trước.</div>
@endif
<form action="{{ route('admin.goods-receipts.store') }}" method="POST" class="card card-body shadow-sm">@csrf
    @if($pr)<input type="hidden" name="purchase_request_id" value="{{ $pr->id }}">@endif
    <div class="row">
        <div class="col-md-5 mb-3"><label class="form-label">Nhà cung cấp *</label>
            <select name="supplier_id" class="form-select" required>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', $pr?->supplier_id) === $s->id)>{{ $s->name }}</option>@endforeach
            </select></div>
        <div class="col-md-3 mb-3"><label class="form-label">Số hóa đơn NCC</label>
            <input name="supplier_invoice_no" value="{{ old('supplier_invoice_no') }}" class="form-control"></div>
        <div class="col-md-4 mb-3"><label class="form-label">Ghi chú</label>
            <input name="note" value="{{ old('note') }}" class="form-control"></div>
    </div>
    <p class="small text-muted">"Đạt" = cây tốt, được cộng vào kho. "Loại" = chết, dập, không đạt (trả NCC, không cộng kho). Để trống dòng không nhập.</p>
    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Cây</th>@if($pr)<th class="text-end">Còn phải nhận</th>@endif<th style="width:120px">Đạt</th><th style="width:120px">Loại</th><th style="width:160px">Đơn giá nhập *</th></tr></thead>
        <tbody>
            @foreach($pr ? $pr->items->where('remaining', '>', 0) : $plants as $row)
                @php $plant = $pr ? $row->plant : $row; @endphp
                <tr>
                    <td>{{ $plant->name }} <span class="small text-muted">{{ $plant->sku }}</span></td>
                    @if($pr)<td class="text-end">{{ $row->remaining }}</td>@endif
                    <td><input type="number" min="0" @if($pr) max="{{ $row->remaining }}" @endif name="lines[{{ $plant->id }}][quantity_received]"
                               value="{{ old("lines.{$plant->id}.quantity_received", $pr ? $row->remaining : '') }}" class="form-control form-control-sm"></td>
                    <td><input type="number" min="0" name="lines[{{ $plant->id }}][quantity_rejected]" value="{{ old("lines.{$plant->id}.quantity_rejected") }}" class="form-control form-control-sm"></td>
                    <td><input type="number" min="0" name="lines[{{ $plant->id }}][unit_cost]"
                               value="{{ old("lines.{$plant->id}.unit_cost", $pr ? $row->estimated_unit_cost : $plant->cost_price) }}" class="form-control form-control-sm"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div><button class="btn btn-green" @disabled($suppliers->isEmpty())>Lưu phiếu nháp</button>
        <a href="{{ $pr ? route('admin.purchase-requests.show', $pr) : route('admin.goods-receipts.index') }}" class="btn btn-outline-secondary">Hủy</a></div>
</form>
@endsection

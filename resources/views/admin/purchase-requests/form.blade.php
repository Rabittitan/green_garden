@extends('layouts.admin')
@section('title', $pr->exists ? 'Sửa yêu cầu '.$pr->code : 'Lập yêu cầu nhập')

@section('content')
<h4 class="text-green fw-bold mb-3">{{ $pr->exists ? 'Sửa yêu cầu '.$pr->code : 'Lập yêu cầu nhập hàng' }}</h4>
<form action="{{ $pr->exists ? route('admin.purchase-requests.update', $pr) : route('admin.purchase-requests.store') }}" method="POST" class="card card-body shadow-sm">
    @csrf @if($pr->exists) @method('PUT') @endif
    <div class="row">
        <div class="col-md-3 mb-3"><label class="form-label">Ưu tiên</label>
            <select name="priority" class="form-select">
                @foreach(['low' => 'Thấp', 'normal' => 'Thường', 'urgent' => 'Gấp'] as $v => $l)<option value="{{ $v }}" @selected(old('priority', $pr->priority) === $v)>{{ $l }}</option>@endforeach
            </select></div>
        <div class="col-md-3 mb-3"><label class="form-label">Cần hàng trước ngày</label>
            <input type="date" name="needed_by" value="{{ old('needed_by', $pr->needed_by?->format('Y-m-d')) }}" class="form-control"></div>
        <div class="col-md-6 mb-3"><label class="form-label">Nhà cung cấp dự kiến</label>
            <select name="supplier_id" class="form-select"><option value="">Chưa chọn</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', $pr->supplier_id) === $s->id)>{{ $s->name }}</option>@endforeach
            </select></div>
        <div class="col-12 mb-3"><label class="form-label">Lý do</label>
            <textarea name="reason" rows="2" class="form-control" placeholder="VD: Sắp Tết, Kim Ngân bán chạy">{{ old('reason', $pr->reason) }}</textarea></div>
    </div>
    <p class="small text-muted">Nhập số lượng cho các cây cần nhập (để trống = không nhập). Cây dưới ngưỡng tồn được đưa lên đầu.</p>
    <div class="table-responsive" style="max-height: 480px">
    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Cây</th><th class="text-end">Tồn / Ngưỡng</th><th style="width:130px">Số lượng</th><th style="width:160px">Đơn giá dự kiến</th><th>Ghi chú</th></tr></thead>
        <tbody>
            @foreach($plants as $p)
                @php $cur = $current->get($p->id); @endphp
                <tr class="{{ $p->stock <= $p->reorder_point ? 'table-warning' : '' }}">
                    <td>{{ $p->name }} <span class="small text-muted">{{ $p->sku }}</span></td>
                    <td class="text-end">{{ $p->stock }} / {{ $p->reorder_point }}</td>
                    <td><input type="number" min="0" name="items[{{ $p->id }}][quantity]" value="{{ old("items.{$p->id}.quantity", $cur?->quantity_requested) }}" class="form-control form-control-sm"></td>
                    <td><input type="number" min="0" name="items[{{ $p->id }}][estimated_unit_cost]" value="{{ old("items.{$p->id}.estimated_unit_cost", $cur?->estimated_unit_cost ?? $p->cost_price) }}" class="form-control form-control-sm"></td>
                    <td><input name="items[{{ $p->id }}][note]" value="{{ old("items.{$p->id}.note", $cur?->note) }}" class="form-control form-control-sm"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    <div><button class="btn btn-green">Lưu nháp</button> <a href="{{ route('admin.purchase-requests.index') }}" class="btn btn-outline-secondary">Hủy</a></div>
</form>
@endsection

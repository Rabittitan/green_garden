@extends('layouts.admin')
@section('title', 'Phiếu kiểm kê')

@section('content')
<h4 class="text-green fw-bold mb-2"><i class="fa fa-clipboard-check"></i> Phiếu kiểm kê</h4>
<p class="small text-muted">Lập phiếu: hệ thống chốt <strong>tồn trên sổ</strong> của từng cây đang bán. Đi đếm thực tế, nhập số đếm (lưu nhiều lần được),
    xem báo cáo thiếu / thừa theo số lượng và giá trị. Bấm <strong>Chốt phiếu</strong> thì kho mới điều chỉnh theo chênh lệch (có ghi vào sổ kho, trỏ về phiếu).
    Hàng bán / nhập trong lúc đang đếm vẫn được tính đúng.</p>
<form action="{{ route('admin.stock-counts.store') }}" method="POST" class="gg-panel gg-panel-body mb-3 d-flex flex-wrap gap-2 align-items-end">@csrf
    <div><label class="form-label small mb-1" for="category_id">Phạm vi</label>
        <select id="category_id" name="category_id" class="form-select form-select-sm">
            <option value="">Mọi cây đang bán</option>
            @foreach($categories as $c)<option value="{{ $c->id }}">Danh mục: {{ $c->name }}</option>@endforeach
        </select></div>
    <div class="flex-grow-1"><label class="form-label small mb-1" for="note">Ghi chú</label>
        <input id="note" name="note" maxlength="255" class="form-control form-control-sm" placeholder="VD: Kiểm kê cuối tháng 11"></div>
    <button class="btn btn-green btn-sm"><i class="fa fa-plus"></i> Lập phiếu kiểm kê</button>
</form>
<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle gg-table-stack">
    <thead class="table-success"><tr><th>Phiếu</th><th>Phạm vi</th><th>Số cây</th><th>Trạng thái</th><th>Lập bởi</th><th></th></tr></thead>
    <tbody>
        @forelse($counts as $c)
            <tr>
                <td data-label="Phiếu" class="fw-bold">{{ $c->code }}<div class="small text-muted">@vndt($c->created_at)</div></td>
                <td data-label="Phạm vi">{{ $c->category->name ?? 'Mọi cây' }}@if($c->note)<div class="small text-muted">{{ $c->note }}</div>@endif</td>
                <td data-label="Số cây">{{ $c->items_count }}</td>
                <td data-label="Trạng thái"><span class="badge {{ ['draft' => 'bg-warning text-dark', 'completed' => 'bg-success', 'cancelled' => 'bg-secondary'][$c->status] }}">{{ $c->statusLabel() }}</span></td>
                <td data-label="Lập bởi">{{ $c->creator->name }}</td>
                <td data-label=""><a href="{{ route('admin.stock-counts.show', $c) }}" class="btn btn-sm btn-outline-success">{{ $c->status === 'draft' ? 'Nhập số đếm' : 'Xem báo cáo' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="6" data-label="" class="text-center text-muted py-4">Chưa có phiếu kiểm kê nào.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
{{ $counts->links() }}
@endsection

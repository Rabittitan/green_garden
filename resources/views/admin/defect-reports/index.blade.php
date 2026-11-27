@extends('layouts.admin')
@section('title', 'Phiếu báo lỗi')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h4 class="text-green fw-bold mb-0"><i class="fa fa-triangle-exclamation"></i> Phiếu báo lỗi</h4>
    <a href="{{ route('admin.defect-reports.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Lập phiếu báo lỗi</a>
</div>
<p class="small text-muted">Nhân viên quét tem từng chậu lỗi (chết, dập, sâu bệnh, nhà cung cấp giao lỗi...), chọn loại lỗi, chụp ảnh.
    Quản trị viên (khác người lập) duyệt thì chậu mới được xuất hủy khỏi kho.</p>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.defect-reports.index') }}" class="btn btn-sm {{ $status === null ? 'btn-success' : 'btn-outline-secondary' }}">Tất cả</a>
    @foreach(\App\Models\DefectReport::STATUSES as $key => [$label])
        <a href="{{ route('admin.defect-reports.index', ['status' => $key]) }}" class="btn btn-sm {{ $status === $key ? 'btn-success' : 'btn-outline-secondary' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
    @endforeach
</div>

<div class="table-responsive">
    <table class="table bg-white shadow-sm align-middle gg-table-stack">
        <thead class="table-success"><tr><th>Mã phiếu</th><th>Ngày lập</th><th>Người lập</th><th class="text-end">Số chậu</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
            @forelse($reports as $r)
                <tr>
                    <td data-label="Mã phiếu" class="fw-semibold">{{ $r->code }}</td>
                    <td data-label="Ngày lập">@vndt($r->created_at)</td>
                    <td data-label="Người lập">{{ $r->creator->name }}</td>
                    <td data-label="Số chậu" class="text-end">{{ $r->items_count }}</td>
                    <td data-label="Trạng thái"><span class="badge {{ $r->statusBadge() }}">{{ $r->statusLabel() }}</span></td>
                    <td data-label=""><a href="{{ route('admin.defect-reports.show', $r) }}" class="btn btn-sm btn-outline-success">Chi tiết</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Chưa có phiếu nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $reports->links() }}
@endsection

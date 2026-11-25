@extends('layouts.admin')
@section('title', 'Flash sale')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h4 class="text-green fw-bold mb-0"><i class="fa fa-bolt"></i> Flash sale</h4>
    @unless(auth()->user()->isAdmin())
        <a href="{{ route('admin.flash-sales.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Tạo đợt sale</a>
    @endunless
</div>
<p class="small text-muted">Nhân viên tạo đợt sale (khung giờ, cây, giá sale, số suất) rồi gửi duyệt; quản trị viên duyệt hoặc từ chối.
    Trong khung giờ, khách mua theo giá sale và <strong>vẫn được</strong> ưu đãi hạng + mã giảm giá. Hết suất thì về giá thường.</p>
<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle gg-table-stack">
    <thead class="table-success"><tr><th>Đợt sale</th><th>Khung giờ</th><th>Số cây</th><th>Trạng thái</th><th>Người tạo</th><th></th></tr></thead>
    <tbody>
        @forelse($sales as $s)
            <tr>
                <td data-label="Đợt sale" class="fw-semibold">{{ $s->name }}</td>
                <td data-label="Khung giờ" class="small">@vndt($s->starts_at) → @vndt($s->ends_at)</td>
                <td data-label="Số cây">{{ $s->items_count }}</td>
                <td data-label="Trạng thái"><span class="badge bg-{{ $s->status->color() }}">{{ $s->phaseLabel() }}</span></td>
                <td data-label="Người tạo">{{ $s->creator->name }}</td>
                <td data-label=""><a href="{{ route('admin.flash-sales.show', $s) }}" class="btn btn-sm btn-outline-success">Chi tiết</a></td>
            </tr>
        @empty
            <tr><td colspan="6" data-label="" class="text-center text-muted py-4">Chưa có đợt flash sale nào.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
{{ $sales->links() }}
@endsection

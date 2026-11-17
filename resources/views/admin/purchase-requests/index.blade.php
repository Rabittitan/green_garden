@extends('layouts.admin')
@section('title', 'Yêu cầu nhập hàng')

@section('content')
<div class="d-flex justify-content-between mb-3">
    <h4 class="text-green fw-bold"><i class="fa fa-clipboard-list"></i> Yêu cầu nhập hàng</h4>
    <div class="d-flex gap-2">
        <form action="{{ route('admin.purchase-requests.from-low-stock') }}" method="POST">@csrf
            <button class="btn btn-outline-warning"><i class="fa fa-wand-magic-sparkles"></i> Lập từ cây dưới ngưỡng</button></form>
        <a href="{{ route('admin.purchase-requests.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Lập yêu cầu</a>
    </div>
</div>
<div class="mb-3 d-flex flex-wrap gap-1">
    <a href="{{ route('admin.purchase-requests.index') }}" class="btn btn-sm {{ request('status') ? 'btn-outline-dark' : 'btn-dark' }}">Tất cả</a>
    @foreach(\App\Enums\PurchaseRequestStatus::cases() as $st)
        <a href="{{ route('admin.purchase-requests.index', ['status' => $st->value]) }}" class="btn btn-sm {{ request('status') === $st->value ? 'btn-success' : 'btn-outline-success' }}">{{ $st->label() }}</a>
    @endforeach
</div>
<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success"><tr><th>Mã</th><th>Người lập</th><th>Ưu tiên</th><th>Cần trước</th><th class="text-end">Số dòng</th><th class="text-end">Dự kiến</th><th>NCC</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
        @forelse($requests as $pr)
            <tr>
                <td class="fw-bold">{{ $pr->code }}</td>
                <td>{{ $pr->requester->name }}<div class="small text-muted">@vndt($pr->created_at)</div></td>
                <td>{{ ['low' => 'Thấp', 'normal' => 'Thường', 'urgent' => 'Gấp'][$pr->priority] }}</td>
                <td>{{ $pr->needed_by?->format('d/m/Y') }}</td>
                <td class="text-end">{{ $pr->items_count }}</td>
                <td class="text-end">@money($pr->estimated_total)</td>
                <td>{{ $pr->supplier?->name }}</td>
                <td><span class="badge bg-{{ $pr->status->color() }}">{{ $pr->status->label() }}</span></td>
                <td><a href="{{ route('admin.purchase-requests.show', $pr) }}" class="btn btn-sm btn-outline-success">Chi tiết</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">Chưa có yêu cầu nhập.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $requests->links() }}
@endsection

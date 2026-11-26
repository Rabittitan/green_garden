@extends('layouts.admin')
@section('title', 'Phiếu báo lỗi '.$report->code)

@section('content')
@php $me = auth()->user(); $total = $report->items->sum('unit_cost'); @endphp
<a href="{{ route('admin.defect-reports.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Phiếu báo lỗi</a>
<div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-3">
    <h4 class="fw-bold mb-0">Phiếu {{ $report->code }}</h4>
    <span class="badge {{ $report->statusBadge() }} fs-6">{{ $report->statusLabel() }}</span>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="table-responsive">
            <table class="table bg-white shadow-sm align-middle gg-table-stack">
                <thead class="table-success"><tr><th>Chậu</th><th>Cây</th><th>Loại lỗi</th><th>Ghi chú</th>@if($report->status === 'approved')<th class="text-end">Giá vốn</th>@endif</tr></thead>
                <tbody>
                    @foreach($report->items as $item)
                        <tr>
                            <td data-label="Chậu"><a href="{{ route('admin.units.index', ['ma' => $item->unit->code]) }}"><code>{{ $item->unit->displayCode() }}</code></a></td>
                            <td data-label="Cây">{{ $item->plant->name }}</td>
                            <td data-label="Loại lỗi">{{ $item->typeLabel() }}</td>
                            <td data-label="Ghi chú">{{ $item->note ?? '—' }}</td>
                            @if($report->status === 'approved')<td data-label="Giá vốn" class="text-end">@money($item->unit_cost ?? 0)</td>@endif
                        </tr>
                    @endforeach
                </tbody>
                @if($report->status === 'approved')
                    <tfoot><tr><th colspan="4" class="text-end">Giá trị thiệt hại</th><th class="text-end text-danger">@money($total)</th></tr></tfoot>
                @endif
            </table>
        </div>
        @if($report->photos)
            <div class="d-flex flex-wrap gap-2">
                @foreach($report->photos as $photo)
                    <a href="{{ $photo }}" target="_blank" rel="noopener"><img src="{{ $photo }}" alt="Ảnh chậu lỗi" class="rounded-3 border" style="width: 160px; height: 120px; object-fit: cover"></a>
                @endforeach
            </div>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="gg-panel gg-panel-body mb-3 small">
            <div>Người lập: <strong>{{ $report->creator->name }}</strong> · @vndt($report->created_at)</div>
            @if($report->decider)<div class="mt-1">{{ $report->status === 'approved' ? 'Người duyệt' : 'Người từ chối' }}: <strong>{{ $report->decider->name }}</strong> · @vndt($report->decided_at)</div>@endif
            @if($report->reject_reason)<div class="mt-1 text-danger">Lý do từ chối: {{ $report->reject_reason }}</div>@endif
            @if($report->note)<div class="mt-2" style="white-space: pre-line">{{ $report->note }}</div>@endif
        </div>

        @if($report->status === 'submitted')
            @if($me->isAdmin() && $me->id !== $report->created_by)
                <form method="POST" action="{{ route('admin.defect-reports.approve', $report) }}" class="gg-panel gg-panel-body mb-3"
                      onsubmit="return confirm('Duyệt và xuất hủy {{ $report->items->count() }} chậu khỏi kho?')">@csrf
                    <button class="btn btn-danger w-100"><i class="fa fa-check"></i> Duyệt &amp; xuất hủy {{ $report->items->count() }} chậu</button>
                </form>
                <form method="POST" action="{{ route('admin.defect-reports.reject', $report) }}" class="gg-panel gg-panel-body">@csrf
                    <label class="form-label small" for="reason">Từ chối (chậu vẫn ở trong kho)</label>
                    <input id="reason" name="reason" required maxlength="255" class="form-control mb-2" placeholder="Lý do">
                    <button class="btn btn-outline-secondary w-100">Từ chối</button>
                </form>
            @elseif($me->id === $report->created_by)
                <div class="alert alert-info small mb-0">Phiếu đang chờ một quản trị viên khác duyệt.</div>
            @else
                <div class="alert alert-info small mb-0">Phiếu đang chờ quản trị viên duyệt.</div>
            @endif
        @endif
    </div>
</div>
@endsection

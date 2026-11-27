@extends('layouts.admin')
@section('title', 'Báo cáo hàng lỗi')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div><span class="gg-eyebrow">Kho</span><h4 class="fw-bold mb-0">Báo cáo hàng lỗi</h4></div>
    <div class="btn-group btn-group-sm" role="group" aria-label="Chọn kỳ">
        @foreach(\App\Http\Controllers\Admin\DefectReportController::PERIODS as $k => [$label])
            <a href="{{ route('admin.defects.report', ['ky' => $k]) }}" class="btn {{ $period === (string) $k ? 'btn-success' : 'btn-outline-success' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
@if($pending > 0)
    <div class="alert alert-warning small">Có {{ $pending }} phiếu báo lỗi đang chờ duyệt (chưa tính vào báo cáo). <a href="{{ route('admin.defect-reports.index', ['status' => 'submitted']) }}">Xem phiếu</a></div>
@endif

<div class="row g-3 mb-3" data-testid="defect-kpis">
    <div class="col-sm-6"><div class="gg-stat"><div class="gg-stat-label">Số chậu xuất hủy · {{ $periodLabel }}</div>
        <div class="gg-stat-value">{{ number_format($summary['qty'], 0, ',', '.') }}</div><small>phiếu báo lỗi đã duyệt + hàng hoàn bị hỏng</small></div></div>
    <div class="col-sm-6"><div class="gg-stat"><div class="gg-stat-label">Giá trị thiệt hại (giá vốn) · {{ $periodLabel }}</div>
        <div class="gg-stat-value text-danger">@money($summary['loss'])</div><small>giá vốn bình quân lúc xuất hủy</small></div></div>
</div>

<div class="row g-3">
    @foreach([['Theo loại lỗi', 'fa-tags', array_values($summary['byType']), 'label', 'by-type'], ['Theo cây', 'fa-leaf', $summary['byPlant'], 'name', 'by-plant'], ['Theo nhà cung cấp (lô nhập)', 'fa-handshake', $summary['bySupplier'], 'name', 'by-supplier']] as [$title, $icon, $rows, $key, $id])
        <div class="col-lg-4">
            <div class="gg-panel h-100">
                <div class="gg-panel-body pb-0 fw-semibold"><i class="fa {{ $icon }}"></i> {{ $title }}</div>
                <table class="table table-sm align-middle mb-0" data-testid="{{ $id }}">
                    <thead><tr><th></th><th class="text-end">Chậu</th><th class="text-end">Thiệt hại</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr><td>{{ $r[$key] }}</td><td class="text-end">{{ $r['qty'] }}</td><td class="text-end">@money($r['loss'])</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">Không có hàng lỗi trong kỳ.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection

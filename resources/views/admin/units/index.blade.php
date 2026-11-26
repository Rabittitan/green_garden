@extends('layouts.admin')
@section('title', 'Tra cứu chậu')

@section('content')
<h4 class="text-green fw-bold mb-2"><i class="fa fa-qrcode"></i> Tra cứu chậu</h4>
<p class="small text-muted">Mỗi chậu có một mã riêng in trên tem QR. Quét tem (hoặc gõ mã) để biết chậu nhập từ phiếu nào, đã giao cho đơn nào, hỏng / thất lạc lúc nào.</p>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="gg-panel gg-panel-body mb-3">
            @include('admin.partials.scanner', ['label' => 'Quét / gõ mã chậu cần tra', 'id' => 'lookup'])
            <form method="GET" class="mt-2 d-none" data-lookup-form><input type="hidden" name="ma"></form>
            @if($query !== '' && ! $unit)
                <div class="alert alert-warning mt-3 mb-0">Không tìm thấy chậu mang mã "{{ $query }}".</div>
            @endif
        </div>

        @if($unit)
            <div class="gg-panel gg-panel-body" data-testid="unit-detail">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="gg-code-big">{{ $unit->displayCode() }}</div>
                        <div class="fw-semibold">{{ $unit->plant->name }} <span class="text-muted small">{{ $unit->plant->sku }}</span></div>
                    </div>
                    <span class="badge {{ $unit->status->badge() }}">{{ $unit->status->label() }}</span>
                </div>
                <dl class="row small mt-3 mb-0">
                    <dt class="col-5">Nhập từ</dt>
                    <dd class="col-7">@if($unit->receiptItem)
                            <a href="{{ route('admin.goods-receipts.show', $unit->receiptItem->receipt) }}">{{ $unit->receiptItem->receipt->code }}</a>
                            · {{ $unit->receiptItem->receipt->supplier?->name }}
                        @else Tồn trước khi có mã / tồn đầu kỳ @endif</dd>
                    @if($unit->orderItem)
                        <dt class="col-5">Đơn đã giao</dt>
                        <dd class="col-7"><a href="{{ route('admin.orders.show', $unit->orderItem->order) }}">{{ $unit->orderItem->order->code }}</a></dd>
                    @endif
                    <dt class="col-5">Phát mã lúc</dt><dd class="col-7">@vndt($unit->created_at)</dd>
                </dl>
                <a href="{{ route('admin.units.labels', ['codes' => $unit->code]) }}" class="btn btn-sm btn-outline-success mt-2"><i class="fa fa-print"></i> In lại tem</a>
            </div>
        @endif
    </div>

    <div class="col-lg-7">
        @if($unit)
            <div class="gg-panel">
                <div class="gg-panel-body pb-0 fw-semibold">Lịch sử chậu</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 gg-table-stack">
                        <thead><tr><th>Lúc</th><th>Việc</th><th>Chứng từ</th><th>Người làm</th></tr></thead>
                        <tbody>
                            @foreach($unit->events as $e)
                                <tr>
                                    <td data-label="Lúc" class="text-nowrap">@vndt($e->created_at)</td>
                                    <td data-label="Việc">{{ $e->label() }}@if($e->note)<div class="small text-muted">{{ $e->note }}</div>@endif</td>
                                    <td data-label="Chứng từ">@if($e->orderItem)<a href="{{ route('admin.orders.show', $e->orderItem->order) }}">{{ $e->orderItem->order->code }}</a>
                                        @elseif($e->movement){{ $e->movement->note }}@else — @endif</td>
                                    <td data-label="Người làm">{{ $e->creator?->name ?? 'Hệ thống' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif($plant && $units)
            <div class="gg-panel">
                <div class="gg-panel-body pb-2">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="fw-semibold">{{ $plant->name }}</div>
                        <a href="{{ route('admin.units.labels', ['plant' => $plant->id]) }}" class="btn btn-sm btn-green"><i class="fa fa-print"></i> In tem chậu trong kho</a>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach(\App\Enums\PlantUnitStatus::cases() as $s)
                            <a href="{{ route('admin.units.index', ['plant' => $plant->id, 'status' => $s->value]) }}"
                               class="btn btn-sm {{ $status === $s ? 'btn-success' : 'btn-outline-secondary' }}">{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</a>
                        @endforeach
                    </div>
                </div>
                <div class="gg-panel-body pt-0">
                    <div class="gg-code-grid">
                        @forelse($units as $u)
                            <a href="{{ route('admin.units.index', ['ma' => $u->code]) }}">{{ $u->displayCode() }}</a>
                        @empty
                            <span class="text-muted small">Không có chậu nào.</span>
                        @endforelse
                    </div>
                    <div class="mt-2">{{ $units->links() }}</div>
                </div>
            </div>
        @else
            <div class="gg-panel gg-panel-body text-muted small">Quét một tem để xem lịch sử. Xem danh sách chậu của một cây ở trang <a href="{{ route('admin.inventory.index') }}">Kho</a> → chọn cây.</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Quét xong -> mở lịch sử chậu đó (mã không có thì scanner đã báo lỗi tại chỗ)
    document.getElementById('lookup').addEventListener('gg:scan', function (e) {
        if (!e.detail.ok) return;
        var f = document.querySelector('[data-lookup-form]'); f.ma.value = e.detail.unit.code; f.submit();
    });
</script>
@endpush

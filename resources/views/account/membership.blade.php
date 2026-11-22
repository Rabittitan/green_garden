@extends('layouts.app')
@section('title', 'Hạng thành viên')

@section('content')
@include('account._nav')
<div class="gg-page-head"><h1><i class="fa fa-crown text-warning"></i> Hạng thành viên</h1></div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="gg-tier-card text-center">
            <div class="text-white-50">Hạng hiện tại</div>
            <div class="display-6 fw-bold">{{ $user->tier->name }}</div>
            @if($user->tier->discount_percent > 0)
                <div>Giảm {{ $user->tier->discount_percent }}% mọi đơn
                    @if($user->tier->max_discount_per_order) (tối đa @money($user->tier->max_discount_per_order)/đơn) @endif</div>
            @endif
            <div class="mt-3">Tổng chi tiêu đã tính: <strong>@money($user->lifetime_spend)</strong></div>
            @if($pendingSpend > 0)
                <div class="small text-white-50">+ @money($pendingSpend) đang chờ hết {{ $returnDays }} ngày đổi trả để được cộng</div>
            @endif
            @if($next)
                <div class="progress mt-3" style="height: 10px"><div class="progress-bar" style="width: {{ $progress }}%"></div></div>
                <div class="small mt-1">Còn <strong>@money($remaining)</strong> để lên hạng <strong>{{ $next->name }}</strong></div>
            @else
                <div class="badge bg-warning text-dark mt-3">Bạn đang ở hạng cao nhất</div>
            @endif
            <div class="small text-white-50 mt-3">Hạng xét theo tổng giá trị đơn đã mua (không tính phí ship và phần được hoàn), giữ vĩnh viễn, không bị hạ.</div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="gg-panel mb-3">
            <div class="gg-panel-head">Quyền lợi các hạng</div>
            <table class="table mb-0">
                <thead><tr><th>Hạng</th><th>Tổng chi tiêu</th><th>Giảm giá</th><th>Miễn phí ship từ</th></tr></thead>
                <tbody>
                @foreach($tiers as $t)
                    <tr class="{{ $t->id === $user->membership_tier_id ? 'table-success fw-bold' : '' }}">
                        <td>{{ $t->name }}</td>
                        <td>@money($t->min_spend)</td>
                        <td>{{ $t->discount_percent }}%{{ $t->max_discount_per_order ? ' (tối đa '.\App\Support\Format::money($t->max_discount_per_order).')' : '' }}</td>
                        <td>{{ $t->free_shipping_min_order === null ? \App\Support\Format::money(config('shop.shipping.free_min_order')) : ($t->free_shipping_min_order === 0 ? 'Mọi đơn' : \App\Support\Format::money($t->free_shipping_min_order)) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="gg-panel">
            <div class="gg-panel-head">Lịch sử hạng</div>
            <ul class="list-group list-group-flush">
                @foreach($histories as $h)
                    <li class="list-group-item small">@vndt($h->created_at) — {{ $h->reason->label() }}:
                        {{ $h->fromTier?->name ?? '—' }} → <strong>{{ $h->toTier->name }}</strong>
                        @if($h->note) <span class="text-muted">({{ $h->note }})</span> @endif</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection

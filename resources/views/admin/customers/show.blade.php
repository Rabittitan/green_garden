@extends('layouts.admin')
@section('title', $customer->name)

@section('content')
<a href="{{ route('admin.customers.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Khách hàng</a>
<h4 class="fw-bold mt-2">{{ $customer->name }} <span class="badge bg-warning text-dark">{{ $customer->tier->name }}</span>
    @unless($customer->is_active)<span class="badge bg-danger">Đã khóa</span>@endunless</h4>
<p>{{ $customer->email }} · {{ $customer->phone }} · Tổng chi tiêu: <strong>@money($customer->lifetime_spend)</strong> · Đăng ký @vndt($customer->created_at)</p>

<div class="row g-4">
    <div class="col-lg-4">
        <form action="{{ route('admin.customers.tier', $customer) }}" method="POST" class="card card-body shadow-sm mb-3">@csrf
            <h6 class="fw-bold">Đổi hạng thủ công</h6>
            <select name="tier_id" class="form-select mb-2">
                @foreach($tiers as $t)<option value="{{ $t->id }}" @selected($t->id === $customer->membership_tier_id)>{{ $t->name }}</option>@endforeach
            </select>
            <input name="note" class="form-control mb-2" placeholder="Lý do (bắt buộc)" required>
            <button class="btn btn-warning">Đổi hạng</button>
            <small class="text-muted mt-1">Hạng tự động chỉ đi lên; đổi tay được cả lên và xuống, có lưu lịch sử.</small>
        </form>
        <form action="{{ route('admin.customers.toggle', $customer) }}" method="POST" class="mb-3">@csrf
            <button class="btn btn-sm {{ $customer->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $customer->is_active ? 'Khóa tài khoản' : 'Mở khóa tài khoản' }}</button>
        </form>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Lịch sử hạng</div>
            <ul class="list-group list-group-flush small">
                @foreach($customer->tierHistories as $h)
                    <li class="list-group-item">@vndt($h->created_at) — {{ $h->reason->label() }}: {{ $h->fromTier?->name ?? '—' }} → <strong>{{ $h->toTier->name }}</strong>
                        <span class="text-muted">(chi tiêu @money($h->spend_total){{ $h->changedBy ? ', bởi '.$h->changedBy->name : '' }}{{ $h->note ? ', '.$h->note : '' }})</span></li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <table class="table bg-white shadow-sm align-middle">
            <thead class="table-success"><tr><th>Mã</th><th>Ngày</th><th>Tổng</th><th>Trạng thái</th><th>Tính vào hạng</th></tr></thead>
            <tbody>
                @foreach($orders as $o)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->code }}</a></td>
                        <td>@vndt($o->placed_at)</td>
                        <td>@money($o->total)</td>
                        <td><span class="badge bg-{{ $o->status->color() }}">{{ $o->status->label() }}</span></td>
                        <td>{{ $o->spend_recorded_at ? \App\Support\Format::money($o->spend_contribution) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $orders->links() }}
    </div>
</div>
@endsection

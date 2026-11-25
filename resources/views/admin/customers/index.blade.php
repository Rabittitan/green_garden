@extends('layouts.admin')
@section('title', 'Khách hàng')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-users"></i> Khách hàng</h4>
<form class="mb-3 d-flex gap-2" method="GET">
    <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:300px" placeholder="Tên / email / SĐT">
    <select name="tier" class="form-select form-select-sm" style="max-width:180px">
        <option value="">Mọi hạng</option>
        @foreach($tiers as $t)<option value="{{ $t->id }}" @selected((int) request('tier') === $t->id)>{{ $t->name }}</option>@endforeach
    </select>
    <button class="btn btn-sm btn-outline-secondary">Lọc</button>
</form>
<p class="small text-muted mb-2"><strong>Đã mua</strong>: tiền khách thực trả cho các đơn đã hoàn thành (trừ tiền đã hoàn, gồm phí ship), cập nhật ngay.
    <strong>Chi tiêu xét hạng</strong>: chỉ cộng đơn đã qua {{ config('shop.return_window_days') }} ngày đổi trả, không gồm phí ship; dùng để lên hạng.</p>
<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success"><tr><th>Khách</th><th>SĐT</th><th>Hạng</th><th class="text-end">Đơn hoàn thành / tổng</th><th class="text-end">Đã mua</th><th class="text-end">Chi tiêu xét hạng</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
        @foreach($customers as $c)
            <tr>
                <td>{{ $c->name }}<div class="small text-muted">{{ $c->email }}</div></td>
                <td>{{ $c->phone }}</td>
                <td><span class="badge bg-warning text-dark">{{ $c->tier->name }}</span></td>
                <td class="text-end">{{ $c->completed_orders_count }} / {{ $c->orders_count }}</td>
                <td class="text-end fw-bold">@money((int) $c->completed_paid_sum - (int) $c->completed_refunded_sum)</td>
                <td class="text-end">@money($c->lifetime_spend)</td>
                <td>{!! $c->is_active ? '<span class="badge bg-success">Hoạt động</span>' : '<span class="badge bg-danger">Đã khóa</span>' !!}</td>
                <td><a href="{{ route('admin.customers.show', $c) }}" class="btn btn-sm btn-outline-success">Chi tiết</a></td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $customers->links() }}
@endsection

@extends('layouts.admin')
@section('title', 'Mã giảm giá')

@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <h4 class="text-green fw-bold"><i class="fa fa-ticket"></i> Mã giảm giá</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.coupon-gifts.index') }}" class="btn btn-outline-success"><i class="fa fa-gift"></i> Quà tặng khi mua</a>
        <a href="{{ route('admin.coupons.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Tạo mã</a>
    </div>
</div>
<ul class="nav nav-pills gap-2 mb-3">
    <li class="nav-item"><a class="nav-link {{ $gifted ? '' : 'active' }}" href="{{ route('admin.coupons.index') }}">Mã chung</a></li>
    <li class="nav-item"><a class="nav-link {{ $gifted ? 'active' : '' }}" href="{{ route('admin.coupons.index', ['loai' => 'tang']) }}">Mã đã tặng riêng cho khách</a></li>
</ul>
<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success"><tr><th>Mã</th><th>Tên</th><th>Ưu đãi</th><th>Điều kiện</th><th>Đã dùng</th><th>Thời hạn</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
        @foreach($coupons as $c)
            <tr>
                <td class="fw-bold">{{ $c->code }}</td>
                <td>{{ $c->name }}@if($c->owner)<div class="small text-muted"><i class="fa fa-gift"></i> của {{ $c->owner->name }}</div>@endif</td>
                <td>{{ match($c->type) { \App\Enums\CouponType::Percent => $c->value.'%', \App\Enums\CouponType::Fixed => \App\Support\Format::money($c->value), \App\Enums\CouponType::FreeShipping => 'Miễn phí ship' } }}
                    @if($c->max_discount_amount)<div class="small text-muted">tối đa @money($c->max_discount_amount)</div>@endif</td>
                <td class="small">
                    @if($c->min_order_amount) Đơn từ @money($c->min_order_amount)<br>@endif
                    @if($c->minTier) Hạng {{ $c->minTier->name }}+<br>@endif
                    @if($c->first_order_only) Đơn đầu tiên<br>@endif
                    @unless($c->combinable_with_tier) Không cộng dồn hạng<br>@endunless
                    {{ $c->scope->value !== 'order' ? 'Theo '.($c->scope->value === 'plants' ? 'cây' : 'danh mục') : '' }}
                </td>
                <td>{{ $c->used_count }}{{ $c->usage_limit ? '/'.$c->usage_limit : '' }} <span class="small text-muted">({{ $c->usage_limit_per_user }}/khách)</span></td>
                <td class="small">@vndt($c->starts_at) – @vndt($c->ends_at)</td>
                <td>@if($c->trashed()) <span class="badge bg-dark">Đã xóa</span>
                    @elseif($c->is_active) <span class="badge bg-success">Bật</span> @else <span class="badge bg-secondary">Tắt</span> @endif</td>
                <td>
                    @unless($c->trashed())
                        <a href="{{ route('admin.coupons.edit', $c) }}" class="btn btn-sm btn-warning"><i class="fa fa-pen"></i></a>
                        <form action="{{ route('admin.coupons.destroy', $c) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa mã {{ $c->code }}? Mã sẽ không dùng lại được.')">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button></form>
                    @endunless
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $coupons->links() }}
@endsection

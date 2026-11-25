@extends('layouts.admin')
@section('title', 'Quà tặng khi mua')

@section('content')
<a href="{{ route('admin.coupons.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Mã giảm giá</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-2">
    <h4 class="text-green fw-bold mb-0"><i class="fa fa-gift"></i> Quà tặng khi mua</h4>
    <a href="{{ route('admin.coupon-gifts.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Thêm luật tặng</a>
</div>
<p class="small text-muted">Đơn <strong>hoàn thành</strong> có tổng tiền từ mức đặt ra, sau khi qua {{ config('shop.return_window_days') }} ngày đổi trả,
    sẽ được tặng 1 mã riêng vào mục "Mã của tôi" của khách (kèm email). Mỗi đơn được tặng 1 lần cho mỗi luật; luật mới chỉ áp cho đơn hoàn thành sau khi tạo.
    Muốn tặng tay cho 1 khách: vào Khách hàng → chi tiết khách → "Tặng mã".</p>
<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle gg-table-stack">
    <thead class="table-success"><tr><th>Luật</th><th>Đơn từ</th><th>Mã được tặng</th><th>Hạn dùng</th><th>Đã tặng</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
        @forelse($rules as $r)
            @php $sample = new \App\Models\Coupon(['type' => $r->type, 'value' => $r->value, 'max_discount_amount' => $r->max_discount_amount]); @endphp
            <tr>
                <td data-label="Luật" class="fw-semibold">{{ $r->name }}</td>
                <td data-label="Đơn từ">@money($r->min_order_total)</td>
                <td data-label="Mã được tặng">{{ $sample->benefitText() }}@if($r->min_order_amount)<div class="small text-muted">dùng cho đơn từ @money($r->min_order_amount)</div>@endif</td>
                <td data-label="Hạn dùng">{{ $r->valid_days }} ngày</td>
                <td data-label="Đã tặng">{{ $r->coupons_count }}</td>
                <td data-label="Trạng thái"><span class="badge {{ $r->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $r->is_active ? 'Đang bật' : 'Tắt' }}</span></td>
                <td data-label=""><a href="{{ route('admin.coupon-gifts.edit', $r) }}" class="btn btn-sm btn-outline-success">Sửa</a></td>
            </tr>
        @empty
            <tr><td colspan="7" data-label="" class="text-center text-muted py-4">Chưa có luật tặng nào.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
@endsection

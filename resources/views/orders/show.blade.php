@extends('layouts.app')
@section('title', 'Đơn hàng ' . $order->code)

@section('content')
<a href="{{ route('orders.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Đơn hàng của tôi</a>
<h4 class="fw-bold mt-2">Đơn hàng {{ $order->code }}
    <span class="badge bg-{{ $order->status->color() }} fs-6">{{ $order->status->label() }}</span>
    <span class="badge bg-{{ $order->payment_status->color() }} fs-6">{{ $order->payment_status->label() }}</span>
</h4>

{{-- ===== VIỆC CẦN LÀM ===== --}}
@if($order->status === \App\Enums\OrderStatus::PendingPayment)
    <div class="alert alert-warning">
        <i class="fa fa-hourglass-half"></i> Đơn đang được giữ hàng tới <strong>@vndt($order->payment_expires_at)</strong>.
        Quá hạn chưa thanh toán, đơn sẽ tự hủy.
        @can('pay', $order)
            <a href="{{ route('orders.pay', $order) }}" class="btn btn-sm btn-warning ms-2"><i class="fa fa-credit-card"></i> Thanh toán @money($order->total)</a>
        @endcan
    </div>
    @if($order->payment_method === \App\Enums\PaymentMethod::BankTransfer)
        <div class="card card-body shadow-sm mb-3 border-warning">
            <h6 class="fw-bold"><i class="fa fa-building-columns"></i> Thông tin chuyển khoản</h6>
            <div>Ngân hàng: <strong>{{ $bank['bank_name'] }}</strong></div>
            <div>Số tài khoản: <strong>{{ $bank['account_no'] ?: '(shop chưa cấu hình)' }}</strong></div>
            <div>Chủ tài khoản: <strong>{{ $bank['account_name'] }}</strong></div>
            <div>Số tiền: <strong class="text-danger">@money($order->total)</strong> (chuyển đúng số tiền)</div>
            <div>Nội dung: <strong>{{ $order->code }}</strong></div>
            <small class="text-muted">Shop xác nhận khi nhận được tiền, sau đó đơn chuyển sang chờ xác nhận.</small>
        </div>
    @endif
@endif
@if($order->payment_status === \App\Enums\PaymentStatus::RefundPending)
    <div class="alert alert-info"><i class="fa fa-rotate-left"></i> Shop đang hoàn @money($order->payments->where('type', \App\Enums\PaymentType::Refund)->where('status', \App\Enums\PaymentState::Pending)->sum('amount')) cho bạn.</div>
@endif

<div class="row g-4 mt-1">
    <div class="col-md-4">
        <div class="card card-body shadow-sm mb-3">
            <h6 class="fw-bold">Thông tin giao hàng</h6>
            <div>{{ $order->recipient_name }} · {{ $order->phone }}</div>
            <div>{{ $order->full_address }}</div>
            @if($order->note) <div class="text-muted mt-2">Ghi chú: {{ $order->note }}</div> @endif
            <div class="text-muted mt-2">Ngày đặt: @vndt($order->placed_at)</div>
            <div class="text-muted">Thanh toán: {{ $order->payment_method->label() }}</div>
            @if($order->tracking_code)
                <div class="mt-2">Vận chuyển: <strong>{{ $order->shipping_carrier }}</strong> · Mã vận đơn <strong>{{ $order->tracking_code }}</strong></div>
            @endif
        </div>

        <div class="card card-body shadow-sm mb-3">
            <h6 class="fw-bold">Tiến trình</h6>
            <ul class="list-unstyled small mb-0">
                @foreach($order->histories as $h)
                    <li class="mb-1"><i class="fa fa-circle-check text-success"></i> @vndt($h->created_at) — {{ $h->to_status->label() }}
                        @if($h->note) <span class="text-muted">({{ $h->note }})</span> @endif</li>
                @endforeach
            </ul>
        </div>

        @can('cancel', $order)
            <form action="{{ route('orders.cancel', $order) }}" method="POST" class="card card-body shadow-sm mb-3"
                  onsubmit="return confirm('Hủy đơn {{ $order->code }}?')">
                @csrf
                <label class="form-label fw-bold">Hủy đơn</label>
                <input name="reason" class="form-control mb-2 @error('reason') is-invalid @enderror" placeholder="Lý do hủy" required maxlength="255">
                <button class="btn btn-outline-danger btn-sm">Hủy đơn hàng</button>
            </form>
        @endcan
        @can('requestReturn', $order)
            <form action="{{ route('orders.return', $order) }}" method="POST" class="card card-body shadow-sm"
                  onsubmit="return confirm('Gửi yêu cầu trả hàng cho đơn {{ $order->code }}?')">
                @csrf
                <label class="form-label fw-bold">Trả hàng (trong {{ config('shop.return_window_days') }} ngày sau khi nhận)</label>
                <input name="reason" class="form-control mb-2" placeholder="Lý do: cây héo, gãy, sai mẫu..." required maxlength="255">
                <button class="btn btn-outline-secondary btn-sm">Yêu cầu trả hàng</button>
            </form>
        @endcan
    </div>

    <div class="col-md-8">
        <div class="table-responsive">
        <table class="table bg-white shadow-sm align-middle">
            <thead class="table-success"><tr><th>Cây</th><th>Đơn giá</th><th>SL</th><th>Giảm</th><th class="text-end">Thành tiền</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->plant_name }}
                            @if($item->returned_good_qty + $item->returned_damaged_qty > 0)
                                <div class="small text-muted">Đã hoàn: {{ $item->returned_good_qty + $item->returned_damaged_qty }}</div>
                            @endif
                        </td>
                        <td>@money($item->unit_price)</td>
                        <td>{{ $item->quantity }}</td>
                        <td class="text-danger">{{ $item->member_discount + $item->coupon_discount > 0 ? '−'.\App\Support\Format::money($item->member_discount + $item->coupon_discount) : '' }}</td>
                        <td class="text-end">@money($item->line_total)</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="text-end">Tạm tính</td><td class="text-end">@money($order->subtotal)</td></tr>
                @if($order->member_discount > 0)
                    <tr class="text-danger"><td colspan="4" class="text-end">Ưu đãi hạng {{ $order->tier->name }} ({{ $order->member_discount_percent }}%)</td><td class="text-end">−@money($order->member_discount)</td></tr>
                @endif
                @if($order->coupon_discount > 0)
                    <tr class="text-danger"><td colspan="4" class="text-end">Mã {{ $order->coupon_code }}</td><td class="text-end">−@money($order->coupon_discount)</td></tr>
                @endif
                <tr><td colspan="4" class="text-end">Phí vận chuyển</td><td class="text-end">{{ $order->shipping_fee > 0 ? \App\Support\Format::money($order->shipping_fee) : 'Miễn phí' }}</td></tr>
                @if($order->shipping_discount > 0)
                    <tr class="text-danger"><td colspan="4" class="text-end">Giảm phí ship ({{ $order->coupon_code }})</td><td class="text-end">−@money($order->shipping_discount)</td></tr>
                @endif
                <tr><th colspan="4" class="text-end">Tổng cộng</th><th class="text-end text-green fs-5">@money($order->total)</th></tr>
                @if($order->paid_amount > 0)
                    <tr><td colspan="4" class="text-end">Đã thanh toán</td><td class="text-end">@money($order->paid_amount)</td></tr>
                @endif
                @if($order->refunded_amount > 0)
                    <tr><td colspan="4" class="text-end">Đã hoàn lại</td><td class="text-end">@money($order->refunded_amount)</td></tr>
                @endif
            </tfoot>
        </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Đơn hàng ' . $order->code)

@section('content')
@php
    use App\Enums\OrderStatus as OS;
    // Thanh tiến trình: mỗi bước là một MỐC đã đạt, chỉ suy ra từ trạng thái hiện tại (không truy vấn).
    // Không có bước "Thanh toán" (đơn COD trả tiền khi nhận hàng); tình trạng tiền xem ở huy hiệu thanh toán.
    // Tên bước KHÁC nhãn trạng thái để chữ "Chờ xác nhận", "Chờ thanh toán"... chỉ xuất hiện khi đơn thật sự ở trạng thái đó.
    $steps = ['Đặt hàng', 'Xác nhận', 'Giao hàng', 'Nhận hàng'];
    $done = match ($order->status) {
        OS::PendingPayment => 0, OS::Pending => 1, OS::Confirmed => 2, OS::Shipping => 3,
        OS::Completed, OS::Returning, OS::Returned => 4, OS::Cancelled => null,
    };
@endphp
@include('account._nav')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="{{ route('orders.index') }}" class="text-green text-decoration-none small"><i class="fa fa-arrow-left"></i> Đơn hàng của tôi</a>
        <h1 class="h3 fw-bold mt-1 mb-0">Đơn hàng {{ $order->code }}</h1>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge rounded-pill bg-{{ $order->status->color() }} fs-6">{{ $order->status->label() }}</span>
        <span class="badge rounded-pill bg-{{ $order->payment_status->color() }} fs-6">{{ $order->payment_status->label() }}</span>
        <a href="{{ route('orders.invoice', $order) }}" class="btn btn-sm btn-outline-green" target="_blank" rel="noopener"><i class="fa fa-print"></i> In hóa đơn</a>
    </div>
</div>

@if($done !== null)
    <div class="gg-panel gg-panel-body mb-3">
        <ol class="gg-progress">
            @foreach($steps as $i => $step)
                <li class="{{ $i < $done ? 'done' : ($i === $done ? 'current' : '') }}">
                    <span>@if($i < $done)<i class="fa fa-check"></i>@else{{ $i + 1 }}@endif</span>{{ $step }}
                </li>
            @endforeach
        </ol>
    </div>
@endif

{{-- ===== VIỆC CẦN LÀM ===== --}}
@if($order->status === OS::Completed && $order->items->contains(fn ($i) => ! isset($myReviews[$i->plant_id]) && $i->plant?->is_active && ! $i->plant->trashed()))
    <div class="alert alert-success"><i class="fa fa-star"></i> Đơn đã giao thành công. Hãy <strong>đánh giá cây bạn đã nhận</strong> để giúp người mua sau. Đánh giá được đăng ngay.</div>
@endif
@if($order->status === \App\Enums\OrderStatus::PendingPayment)
    <div class="alert alert-warning">
        <i class="fa fa-hourglass-half"></i> Đơn đang được giữ hàng tới <strong>@vndt($order->payment_expires_at)</strong>.
        Quá hạn chưa thanh toán, đơn sẽ tự hủy.
        @can('pay', $order)
            {{-- POST + CSRF: mở giao dịch thanh toán là thao tác ghi, không để link GET (prefetch / site khác nhúng) kích hoạt --}}
            <form method="POST" action="{{ route('orders.pay', $order) }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-warning ms-2"><i class="fa fa-credit-card"></i> Thanh toán @money($order->total)</button>
            </form>
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
@if($order->status === \App\Enums\OrderStatus::Returning && $order->completed_at)
    <div class="alert alert-secondary">
        <i class="fa fa-box"></i> <strong>Hướng dẫn trả hàng:</strong> gửi cây về <strong>{{ config('shop.return_address') }}</strong>,
        ghi mã đơn <strong>{{ $order->code }}</strong> bên ngoài gói hàng. Shop kiểm tra khi nhận và hoàn tiền theo tình trạng thực tế.
    </div>
@endif
@if($order->payment_status === \App\Enums\PaymentStatus::RefundPending)
    <div class="alert alert-info"><i class="fa fa-rotate-left"></i> Shop đang hoàn @money($order->payments->where('type', \App\Enums\PaymentType::Refund)->where('status', \App\Enums\PaymentState::Pending)->sum('amount')) cho bạn.</div>
@endif

<div class="row g-4 mt-1">
    <div class="col-md-4">
        <div class="gg-panel gg-panel-body mb-3">
            <h2 class="h6 fw-bold">Thông tin giao hàng</h2>
            <div>{{ $order->recipient_name }} · {{ $order->phone }}</div>
            <div>{{ $order->full_address }}</div>
            <div class="text-muted mt-2">Ngày đặt: @vndt($order->placed_at)</div>
            <div class="text-muted">Thanh toán: {{ $order->payment_method->label() }}</div>
            @if($order->tracking_code)
                <div class="mt-2">Vận chuyển: <strong>{{ $order->shipping_carrier }}</strong> · Mã vận đơn <strong>{{ $order->tracking_code }}</strong></div>
            @endif
        </div>

        {{-- Ghi chú cho shop: sửa được tới khi shop xác nhận đơn --}}
        <div class="gg-panel gg-panel-body mb-3" id="ghi-chu">
            <h2 class="h6 fw-bold"><i class="fa fa-note-sticky text-green"></i> Ghi chú cho shop</h2>
            @can('updateNote', $order)
                <form action="{{ route('orders.note', $order) }}" method="POST">
                    @csrf
                    <label class="visually-hidden" for="order-note">Ghi chú cho shop</label>
                    <textarea id="order-note" name="note" rows="3" maxlength="1000" class="form-control mb-2 @error('note') is-invalid @enderror"
                              placeholder="VD: giao giờ hành chính, gọi trước khi giao, gói làm quà...">{{ old('note', $order->note) }}</textarea>
                    @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <button class="btn btn-sm btn-green">Lưu ghi chú</button>
                    <span class="small text-muted ms-1">Sửa được tới khi shop xác nhận đơn.</span>
                </form>
            @else
                <div class="{{ $order->note ? '' : 'text-muted' }}" style="white-space: pre-line">{{ $order->note ?: 'Không có ghi chú.' }}</div>
            @endcan
        </div>

        <div class="gg-panel gg-panel-body mb-3">
            <h2 class="h6 fw-bold mb-3">Tiến trình</h2>
            <ul class="gg-timeline mb-0">
                @foreach($order->histories as $h)
                    <li><span class="text-muted">@vndt($h->created_at)</span> — {{ $h->to_status->label() }}
                        @if($h->note) <span class="text-muted">({{ $h->note }})</span> @endif</li>
                @endforeach
            </ul>
        </div>

        @can('cancel', $order)
            <form action="{{ route('orders.cancel', $order) }}" method="POST" class="gg-panel gg-panel-body mb-3"
                  onsubmit="return confirm('Hủy đơn {{ $order->code }}?')">
                @csrf
                <label class="form-label fw-bold">Hủy đơn</label>
                <input name="reason" class="form-control mb-2 @error('reason') is-invalid @enderror" placeholder="Lý do hủy" required maxlength="255">
                <button class="btn btn-outline-danger btn-sm">Hủy đơn hàng</button>
            </form>
        @endcan
        @can('requestReturn', $order)
            <form action="{{ route('orders.return', $order) }}" method="POST" class="gg-panel gg-panel-body"
                  onsubmit="return confirm('Gửi yêu cầu trả hàng cho đơn {{ $order->code }}?')">
                @csrf
                <label class="form-label fw-bold">Trả hàng (trong {{ config('shop.return_window_days') }} ngày sau khi nhận)</label>
                <input name="reason" class="form-control mb-2" placeholder="Lý do: cây héo, gãy, sai mẫu..." required maxlength="255">
                <button class="btn btn-outline-secondary btn-sm">Yêu cầu trả hàng</button>
            </form>
        @endcan
    </div>

    <div class="col-md-8">
        <div class="gg-panel table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr class="small text-muted"><th>Cây</th><th>Đơn giá</th><th>SL</th><th>Giảm</th><th class="text-end">Thành tiền</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->plant_name }}
                            @if($item->returned_good_qty + $item->returned_damaged_qty > 0)
                                <div class="small text-muted">Đã hoàn: {{ $item->returned_good_qty + $item->returned_damaged_qty }}</div>
                            @endif
                            {{-- Đơn đã nhận hàng: mời đánh giá (chỉ khi cây còn bán, vì trang cây ngừng bán trả 404) --}}
                            @if($order->status === OS::Completed && $item->plant?->is_active && ! $item->plant->trashed())
                                @php $mine = $myReviews[$item->plant_id] ?? null; @endphp
                                <div class="mt-1">
                                    @if($mine)
                                        <a href="{{ route('plants.show', $item->plant_id) }}#reviews" class="btn btn-sm btn-outline-secondary py-0">
                                            <span class="gg-stars">{{ str_repeat('★', $mine->rating) }}</span> Đã đánh giá · Sửa</a>
                                    @else
                                        <a href="{{ route('plants.show', $item->plant_id) }}#reviews" class="btn btn-sm btn-green py-0"><i class="fa fa-star"></i> Đánh giá cây này</a>
                                    @endif
                                </div>
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

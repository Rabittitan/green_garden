{{--
    Hóa đơn bán lẻ của 1 đơn hàng: trang riêng, không có header/footer website, tối ưu cho khổ A4 khi in / lưu PDF.
    Dùng chung cho khách (orders.invoice) và nhân viên (admin.orders.invoice). Biến: $order (đã nạp items, tier), $backUrl.
    Chỉ đọc. Đây KHÔNG phải hóa đơn GTGT (hóa đơn điện tử theo quy định thuế).
--}}
@php
    use App\Enums\OrderStatus as OS;
    $seller = config('shop.seller');
    $warning = match ($order->status) {
        OS::Cancelled => 'ĐƠN ĐÃ HỦY',
        OS::Returned => 'ĐƠN ĐÃ HOÀN HÀNG',
        OS::PendingPayment => 'CHỜ THANH TOÁN',
        default => null,
    };
    $paidLabel = $order->paid_amount >= $order->total ? 'Đã thanh toán đủ' : ($order->paid_amount > 0 ? 'Đã thanh toán một phần' : 'Chưa thanh toán');
    $due = max(0, $order->total - $order->paid_amount);
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Hóa đơn {{ $order->code }} - {{ $seller['name'] }}</title>
    <link href="{{ asset('css/invoice.css') }}?v={{ @filemtime(public_path('css/invoice.css')) }}" rel="stylesheet">
</head>
<body>
<div class="inv-actions no-print">
    <a href="{{ $backUrl }}">← Quay lại đơn hàng</a>
    <button type="button" id="print-btn">In hóa đơn / Lưu PDF</button>
</div>

<main class="inv">
    @if($warning)<div class="inv-stamp">{{ $warning }}</div>@endif

    <header class="inv-head">
        <div>
            <div class="inv-brand">{{ $seller['name'] }}</div>
            @if($seller['address'])<div>{{ $seller['address'] }}</div>@endif
            @if($seller['phone'] || $seller['email'])
                <div>{{ collect([$seller['phone'] ? 'ĐT: '.$seller['phone'] : null, $seller['email'] ?: null])->filter()->implode(' · ') }}</div>
            @endif
            @if($seller['tax_code'])<div>Mã số thuế: {{ $seller['tax_code'] }}</div>@endif
        </div>
        <div class="inv-title">
            <h1>HÓA ĐƠN BÁN LẺ</h1>
            <div>Số: <strong>{{ $order->code }}</strong></div>
            <div>Ngày đặt: @vndt($order->placed_at)</div>
            <div>Ngày in: @vndt(now())</div>
        </div>
    </header>

    <section class="inv-parties">
        <div>
            <h2>Người nhận</h2>
            <div><strong>{{ $order->recipient_name }}</strong> · {{ $order->phone }}</div>
            <div>{{ $order->full_address }}</div>
        </div>
        <div>
            <h2>Đơn hàng</h2>
            <div>Trạng thái: {{ $order->status->label() }}</div>
            <div>Thanh toán: {{ $order->payment_method->label() }} — {{ $paidLabel }}</div>
            @if($order->tracking_code)<div>Vận chuyển: {{ $order->shipping_carrier }} · {{ $order->tracking_code }}</div>@endif
        </div>
    </section>

    @if($order->note)
        <section class="inv-note"><strong>Ghi chú của khách:</strong> <span>{{ $order->note }}</span></section>
    @endif

    <table class="inv-table">
        <thead><tr><th>#</th><th>Sản phẩm</th><th class="r">Đơn giá</th><th class="r">SL</th><th class="r">Giảm</th><th class="r">Thành tiền</th></tr></thead>
        <tbody>
            @foreach($order->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->plant_name }}<div class="muted">{{ $item->plant_sku }}</div></td>
                    <td class="r">@money($item->unit_price)</td>
                    <td class="r">{{ $item->quantity }}</td>
                    <td class="r">{{ $item->member_discount + $item->coupon_discount > 0 ? '−'.\App\Support\Format::money($item->member_discount + $item->coupon_discount) : '' }}</td>
                    <td class="r">@money($item->line_total)</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="inv-sum">
        <tr><td>Tạm tính</td><td class="r">@money($order->subtotal)</td></tr>
        @if($order->member_discount > 0)
            <tr><td>Ưu đãi hạng {{ $order->tier?->name }} ({{ $order->member_discount_percent }}%)</td><td class="r">−@money($order->member_discount)</td></tr>
        @endif
        @if($order->coupon_discount > 0)
            <tr><td>Mã giảm giá {{ $order->coupon_code }}</td><td class="r">−@money($order->coupon_discount)</td></tr>
        @endif
        <tr><td>Phí vận chuyển</td><td class="r">{{ $order->shipping_fee > 0 ? \App\Support\Format::money($order->shipping_fee) : 'Miễn phí' }}</td></tr>
        @if($order->shipping_discount > 0)
            <tr><td>Giảm phí vận chuyển ({{ $order->coupon_code }})</td><td class="r">−@money($order->shipping_discount)</td></tr>
        @endif
        <tr class="total"><td>Tổng cộng</td><td class="r">@money($order->total)</td></tr>
        <tr><td>Đã thanh toán</td><td class="r">@money($order->paid_amount)</td></tr>
        @if($order->refunded_amount > 0)
            <tr><td>Đã hoàn lại</td><td class="r">@money($order->refunded_amount)</td></tr>
        @endif
        @if($due > 0 && ! in_array($order->status, [OS::Cancelled, OS::Returned], true))
            <tr class="due"><td>Còn phải thu{{ $order->payment_method === \App\Enums\PaymentMethod::Cod ? ' khi giao (COD)' : '' }}</td><td class="r">@money($due)</td></tr>
        @endif
    </table>

    <footer class="inv-foot">
        <p>Cảm ơn bạn đã mua cây tại {{ $seller['name'] }}! Đổi trả trong {{ config('shop.return_window_days') }} ngày kể từ khi nhận hàng.</p>
        <p class="muted">Hóa đơn bán lẻ, không thay thế hóa đơn giá trị gia tăng (GTGT).</p>
    </footer>
</main>
<script>document.getElementById('print-btn').addEventListener('click', function () { window.print(); });</script>
</body>
</html>

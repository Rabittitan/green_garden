@php
    $money = fn ($v) => number_format((int) $v, 0, ',', '.').'đ';
    $code = $order->code ?? '#'.$order->id;
@endphp
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f4;font-family:Arial,Helvetica,sans-serif;color:#333">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f4;padding:24px 0">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden">

    {{-- Đầu thư --}}
    <tr><td style="background:#2e7d32;padding:20px 24px;color:#ffffff">
        <div style="font-size:22px;font-weight:bold">🌱 GreenGarden Shop</div>
        <div style="font-size:14px;opacity:.9">Cây cảnh phong thủy &amp; nội thất</div>
    </td></tr>

    <tr><td style="padding:24px">
        <h2 style="margin:0 0 8px;color:#2e7d32;font-size:20px">Đặt hàng thành công!</h2>
        <p style="margin:0 0 16px;line-height:1.6">
            Xin chào <strong>{{ $order->customer_name }}</strong>,<br>
            Cảm ơn bạn đã mua cây tại GreenGarden. Đơn hàng <strong>{{ $code }}</strong> đã được ghi nhận
            lúc {{ $order->created_at->format('H:i d/m/Y') }} và đang chờ shop xác nhận.
        </p>

        {{-- Chi tiết sản phẩm --}}
        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px">
            <tr style="background:#e8f5e9">
                <th align="left">Sản phẩm</th><th align="center">SL</th><th align="right">Đơn giá</th><th align="right">Thành tiền</th>
            </tr>
            @foreach($order->items as $item)
                <tr style="border-bottom:1px solid #eee">
                    <td>{{ $item->plant_name ?? $item->plant?->name }}</td>
                    <td align="center">{{ $item->quantity }}</td>
                    <td align="right">{{ $money($item->price) }}</td>
                    <td align="right">{{ $money($item->price * $item->quantity) }}</td>
                </tr>
            @endforeach
        </table>

        {{-- Tổng tiền: các cột subtotal/discount/shipping có ở DB v2, nếu chưa có thì chỉ hiện tổng --}}
        <table width="100%" cellpadding="4" cellspacing="0" style="font-size:14px;margin-top:8px">
            @if(isset($order->subtotal) && $order->subtotal > 0)
                <tr><td align="right">Tạm tính:</td><td align="right" width="140">{{ $money($order->subtotal) }}</td></tr>
                @if($order->discount_amount > 0)
                    <tr><td align="right">Giảm giá{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}:</td>
                        <td align="right" style="color:#c62828">-{{ $money($order->discount_amount) }}</td></tr>
                @endif
                <tr><td align="right">Phí vận chuyển:</td>
                    <td align="right">{{ $order->shipping_fee > 0 ? $money($order->shipping_fee) : 'Miễn phí' }}</td></tr>
            @endif
            <tr><td align="right" style="font-size:16px;font-weight:bold">Tổng thanh toán:</td>
                <td align="right" style="font-size:18px;font-weight:bold;color:#2e7d32">{{ $money($order->total) }}</td></tr>
        </table>

        {{-- Thông tin giao hàng --}}
        <div style="margin-top:20px;padding:12px 16px;background:#f8faf8;border-left:4px solid #2e7d32;font-size:14px;line-height:1.7">
            <strong>Thông tin giao hàng</strong><br>
            Người nhận: {{ $order->customer_name }}<br>
            Số điện thoại: {{ $order->phone }}<br>
            Địa chỉ: {{ $order->address }}<br>
            @if($order->note) Ghi chú: {{ $order->note }}<br> @endif
            Thanh toán: Thanh toán khi nhận hàng (COD)
        </div>

        <p style="text-align:center;margin:24px 0 8px">
            <a href="{{ route('orders.show', $order->id) }}"
               style="background:#2e7d32;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;display:inline-block;font-weight:bold">
                Xem chi tiết đơn hàng
            </a>
        </p>
    </td></tr>

    {{-- Chân thư --}}
    <tr><td style="background:#1b5e20;color:#c8e6c9;padding:16px 24px;font-size:12px;text-align:center;line-height:1.6">
        Cần hỗ trợ? Hotline 0900 000 001 · lienhe@GreenGarden.test<br>
        Bạn nhận được email này vì đã đặt hàng tại GreenGarden Shop.
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
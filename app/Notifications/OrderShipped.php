<?php

namespace App\Notifications;

class OrderShipped extends OrderNotification
{
    protected function subject(): string
    {
        return 'Đơn hàng đang được giao';
    }

    protected function lines(): array
    {
        return ['Đơn '.$this->order->code.' đã được giao cho '.$this->order->shipping_carrier.', mã vận đơn: '.$this->order->tracking_code.'.']
            + ($this->order->payment_method->value === 'cod' ? [1 => 'Vui lòng chuẩn bị '.$this->money($this->order->total).' để thanh toán khi nhận hàng.'] : []);
    }
}

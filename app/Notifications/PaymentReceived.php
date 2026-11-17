<?php

namespace App\Notifications;

class PaymentReceived extends OrderNotification
{
    protected function subject(): string
    {
        return 'Đã nhận thanh toán';
    }

    protected function lines(): array
    {
        return ['Shop đã nhận '.$this->money($this->order->paid_amount).' cho đơn '.$this->order->code.'.', 'Đơn đang chờ shop xác nhận và đóng gói.'];
    }
}

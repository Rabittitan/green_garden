<?php

namespace App\Notifications;

class OrderCancelled extends OrderNotification
{
    protected function subject(): string
    {
        return 'Đơn hàng đã bị hủy';
    }

    protected function lines(): array
    {
        $lines = ['Đơn '.$this->order->code.' đã bị hủy. Lý do: '.$this->order->cancel_reason.'.'];
        if ($this->order->paid_amount > $this->order->refunded_amount) {
            $lines[] = 'Shop sẽ hoàn lại '.$this->money($this->order->paid_amount - $this->order->refunded_amount).' cho bạn và gửi email khi hoàn tất.';
        }

        return $lines;
    }
}

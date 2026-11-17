<?php

namespace App\Notifications;

class ReturnInstructions extends OrderNotification
{
    protected function subject(): string
    {
        return 'Hướng dẫn trả hàng';
    }

    protected function lines(): array
    {
        return [
            'Shop đã nhận yêu cầu trả hàng cho đơn '.$this->order->code.'.',
            'Vui lòng gửi cây về: '.config('shop.return_address').'. Ghi mã đơn '.$this->order->code.' bên ngoài gói hàng.',
            'Shop kiểm tra cây khi nhận và hoàn tiền theo tình trạng thực tế.',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Enums\PaymentMethod;
use App\Support\Format;

/** Giao thành công: với COD đây là lúc tiền được thu; với mọi đơn, bắt đầu tính hạn đổi trả. */
class OrderCompleted extends OrderNotification
{
    protected function subject(): string
    {
        return 'Giao hàng thành công';
    }

    protected function lines(): array
    {
        $lines = ['Đơn '.$this->order->code.' đã được giao thành công. Cảm ơn bạn đã mua cây tại GreenGarden!'];
        if ($this->order->payment_method === PaymentMethod::Cod) {
            $lines[] = 'Bạn đã thanh toán '.$this->money($this->order->paid_amount).' tiền mặt khi nhận hàng.';
        }
        $deadline = $this->order->completed_at?->copy()->addDays((int) config('shop.return_window_days'));
        $lines[] = 'Nếu cây có vấn đề, bạn có thể yêu cầu trả hàng trên trang đơn đến hết '.Format::datetime($deadline).'.';
        $lines[] = 'Cây đã ổn định? Hãy vào trang đơn và bấm "Đánh giá cây này" để chia sẻ cảm nhận với người mua sau.';

        return $lines;
    }
}

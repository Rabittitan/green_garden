<?php

namespace App\Notifications;

use App\Models\Order;

/** Tiền về SAU khi đơn đã hủy/hết hạn: shop đã nhận tiền nhưng không giao -> báo khách sẽ được hoàn. */
class LatePaymentRefund extends OrderNotification
{
    public function __construct(Order $order, public int $amount)
    {
        parent::__construct($order);
    }

    protected function subject(): string
    {
        return 'Thanh toán đến sau khi đơn đã hủy';
    }

    protected function lines(): array
    {
        return [
            'Shop đã nhận '.$this->money($this->amount).' cho đơn '.$this->order->code.', nhưng đơn đã bị hủy trước đó (quá hạn thanh toán hoặc đã hủy).',
            'Bạn sẽ được hoàn lại toàn bộ số tiền này; shop sẽ gửi email khi hoàn tiền xong.',
            'Nếu vẫn muốn mua, vui lòng đặt đơn mới.',
        ];
    }
}

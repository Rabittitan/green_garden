<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Payment;

class RefundCompleted extends OrderNotification
{
    public function __construct(Order $order, public Payment $refund)
    {
        parent::__construct($order);
    }

    protected function subject(): string
    {
        return 'Đã hoàn tiền';
    }

    protected function lines(): array
    {
        return ['Shop đã hoàn '.$this->money($this->refund->amount).' cho đơn '.$this->order->code.' (mã giao dịch '.$this->refund->provider_txn_no.').',
            'Thời gian tiền về tài khoản tùy ngân hàng, thường 1-7 ngày làm việc.'];
    }
}

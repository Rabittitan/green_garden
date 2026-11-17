<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phát ra SAU KHI đơn hàng đã lưu thành công.
 * Ai cần phản ứng khi có đơn mới (gửi mail cho khách, báo cho admin, ...) thì viết Listener nghe event này,
 * không phải sửa CheckoutController.
 */
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}

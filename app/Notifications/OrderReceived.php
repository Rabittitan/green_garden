<?php

namespace App\Notifications;

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Mail "Đặt hàng thành công" (mẫu đầy đủ: danh sách cây, tiền, địa chỉ — view emails.orders.placed).
 * Gửi qua App\Support\Notifier như mọi mail khác (ADR-007): chỉ sau khi commit, qua hàng đợi, lỗi gửi không làm hỏng đơn.
 * Thay cho sự kiện OrderPlaced + listener SendOrderPlacedEmail trước đây (đường gửi riêng, không qua Notifier).
 */
class OrderReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;               // gửi lỗi thì thử lại tối đa 3 lần

    /** @var list<int> */
    public array $backoff = [60, 300];   // lần 2 sau 1 phút, lần 3 sau 5 phút

    public function __construct(public Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): OrderPlacedMail
    {
        $order = $this->order->loadMissing(['user', 'items.plant']);

        return (new OrderPlacedMail($order))->to($order->user->email, $order->recipient_name);
    }
}

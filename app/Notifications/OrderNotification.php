<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Khung chung cho mail về 1 đơn hàng: tiêu đề + các dòng nội dung + nút xem đơn. */
abstract class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    /** @return list<string> */
    abstract protected function lines(): array;

    abstract protected function subject(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject().' - Đơn '.$this->order->code.' - GreenLife')
            ->greeting('Xin chào '.$this->order->recipient_name.',');
        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail->action('Xem đơn hàng', route('orders.show', $this->order))
            ->salutation('Trân trọng, GreenLife Shop');
    }

    protected function money(int $v): string
    {
        return Format::money($v);
    }
}

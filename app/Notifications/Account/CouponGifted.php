<?php

namespace App\Notifications\Account;

use App\Models\Coupon;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Báo khách vừa được tặng 1 mã giảm giá riêng (nằm trong "Mã của tôi"). */
class CouponGifted extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Coupon $coupon, public string $reason) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->coupon;
        $mail = (new MailMessage)
            ->subject('Bạn được tặng mã giảm giá '.$c->code.' - GreenGarden')
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line($this->reason)
            ->line('Mã của bạn: '.$c->code.' — '.$c->benefitText().'.');
        if ($c->min_order_amount > 0) {
            $mail->line('Áp dụng cho đơn từ '.Format::money($c->min_order_amount).'.');
        }

        return $mail->line('Hạn dùng đến hết '.Format::datetime($c->ends_at, 'd/m/Y').'. Mã chỉ dùng được với tài khoản của bạn, 1 lần.')
            ->action('Xem mã của tôi', route('account.coupons'))
            ->salutation('Trân trọng, GreenGarden Shop');
    }
}

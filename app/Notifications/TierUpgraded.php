<?php

namespace App\Notifications;

use App\Models\MembershipTier;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TierUpgraded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MembershipTier $tier) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->tier;

        return (new MailMessage)
            ->subject('Chúc mừng bạn lên hạng '.$t->name.' - GreenLife')
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line('Bạn vừa lên hạng thành viên '.$t->name.'.')
            ->line('Quyền lợi: giảm '.$t->discount_percent.'% mọi đơn'.($t->max_discount_per_order ? ' (tối đa '.Format::money($t->max_discount_per_order).'/đơn)' : '').'.')
            ->action('Xem hạng thành viên', route('account.membership'))
            ->salutation('Trân trọng, GreenLife Shop');
    }
}

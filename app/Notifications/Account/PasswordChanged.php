<?php

namespace App\Notifications\Account;

use App\Support\Format;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Cảnh báo bảo mật: mật khẩu vừa đổi (trong trang tài khoản hoặc qua "Quên mật khẩu"). */
class PasswordChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public DateTimeInterface $changedAt, public bool $viaReset = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mật khẩu tài khoản đã được thay đổi - GreenLife')
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line('Mật khẩu tài khoản GreenLife của bạn vừa được '.($this->viaReset ? 'đặt lại qua chức năng "Quên mật khẩu"' : 'thay đổi trong trang tài khoản')
                .' lúc '.Format::datetime($this->changedAt).'.')
            ->line($this->viaReset ? 'Mọi phiên đăng nhập cũ đã được đăng xuất.' : 'Nếu đó là bạn, bạn không cần làm gì thêm.')
            ->line('Nếu KHÔNG phải bạn thực hiện, hãy đặt lại mật khẩu ngay và liên hệ shop.')
            ->action('Đặt lại mật khẩu', route('password.request'))
            ->salutation('Trân trọng, GreenLife Shop');
    }
}

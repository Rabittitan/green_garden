<?php

namespace App\Notifications\Account;

use App\Support\Format;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Gửi tới EMAIL CŨ khi tài khoản đổi email: chủ tài khoản thật vẫn biết nếu bị chiếm tài khoản.
 * Email mới chỉ hiện dạng che bớt (không lộ đầy đủ địa chỉ cho người đang giữ hộp thư cũ).
 */
class EmailChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $name, public string $newEmail, public DateTimeInterface $changedAt) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

        return $keep.str_repeat('*', max(3, mb_strlen($local) - mb_strlen($keep))).'@'.$domain;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Email đăng nhập tài khoản đã được thay đổi - GreenGarden')
            ->greeting('Xin chào '.$this->name.',')
            ->line('Email đăng nhập tài khoản GreenGarden của bạn vừa được đổi sang '.self::mask($this->newEmail)
                .' lúc '.Format::datetime($this->changedAt).'.')
            ->line('Từ nay thông báo đơn hàng sẽ gửi tới email mới; email này sẽ không nhận thêm thông báo nào.')
            ->line('Nếu KHÔNG phải bạn thực hiện, hãy liên hệ shop ngay để được hỗ trợ khóa và lấy lại tài khoản.')
            ->salutation('Trân trọng, GreenGarden Shop');
    }
}

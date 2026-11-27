<?php

namespace App\Notifications\Admin;

use App\Support\Format;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Đối soát dữ liệu (shop:reconcile, 02:17 hằng đêm) phát hiện lệch: báo mọi quản trị viên đang hoạt động. */
class ReconcileFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, list<int|string>> $failures  tên kiểm tra => id các dòng lệch (tối đa 20) */
    public function __construct(public array $failures, public DateTimeInterface $at) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->error()
            ->subject('[Cần xử lý] Đối soát dữ liệu phát hiện '.count($this->failures).' mục lệch - GreenGarden')
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line('Lần đối soát lúc '.Format::datetime($this->at).' phát hiện dữ liệu không khớp:');
        foreach ($this->failures as $label => $ids) {
            $mail->line('• '.$label.': '.implode(', ', $ids));
        }

        return $mail
            ->line('Không sửa tay trong DB. Làm theo mục "Khi đối soát báo lệch" trong tài liệu vận hành (docs/H7-van-hanh.md), rồi chạy lại: php artisan shop:reconcile')
            ->action('Mở trang quản trị', route('admin.dashboard'))
            ->salutation('Hệ thống GreenGarden');
    }
}

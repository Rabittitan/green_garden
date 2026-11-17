<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gửi thông báo cho khách SAU KHI transaction commit (không bao giờ báo "đã thanh toán" cho dữ liệu bị rollback).
 * Lỗi gửi (SMTP...) chỉ ghi log, không làm hỏng nghiệp vụ đã hoàn tất.
 */
final class Notifier
{
    public static function send(User $user, Notification $notification): void
    {
        DB::afterCommit(function () use ($user, $notification) {
            try {
                $user->notify($notification);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}

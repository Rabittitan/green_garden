<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderPlacedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Gửi mail xác nhận đơn cho khách.
 *
 * ShouldQueue: mail được đẩy vào hàng đợi (bảng jobs), khách không phải chờ SMTP.
 *   - Cần chạy worker:  php artisan queue:work
 *   - Hoặc trong .env đặt QUEUE_CONNECTION=sync để gửi ngay (chậm hơn, không cần worker).
 *
 * Laravel 11+ tự tìm Listener trong app/Listeners theo kiểu tham số của handle(), không cần đăng ký.
 */
class SendOrderPlacedEmail implements ShouldQueue
{
    public int $tries = 3;               // gửi lỗi thì thử lại tối đa 3 lần

    public array $backoff = [60, 300];   // lần 2 sau 1 phút, lần 3 sau 5 phút

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing(['user', 'items.plant']);

        Mail::to($order->user->email, $order->customer_name)->send(new OrderPlacedMail($order));
    }

    public function failed(OrderPlaced $event, \Throwable $e): void
    {
        Log::error('Gửi mail xác nhận đơn thất bại', ['order_id' => $event->order->id, 'error' => $e->getMessage()]);
    }
}

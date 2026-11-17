<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Shipping = 'shipping';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xử lý',
            self::Shipping => 'Đang giao',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    /** Máy trạng thái: chỉ những bước chuyển này là hợp lệ. */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Pending => [self::Shipping, self::Cancelled],
            self::Shipping => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        }, true);
    }
}

<?php

namespace App\Enums;

/**
 * Trạng thái của 1 dòng payments.
 *   pending -> succeeded | failed | cancelled
 *   cancelled -> succeeded   CHỈ khi cổng thanh toán báo thành công trễ (IPN đến sau khi đơn đã hết hạn):
 *                            tiền đã bị trừ thật thì phải ghi nhận để hoàn lại.
 */
enum PaymentState: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Đang chờ',
            self::Succeeded => 'Thành công',
            self::Failed => 'Thất bại',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function canTransitionTo(self $to, bool $confirmedByGateway = false): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Succeeded, self::Failed, self::Cancelled], true),
            self::Cancelled => $to === self::Succeeded && $confirmedByGateway,
            self::Succeeded, self::Failed => false,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}

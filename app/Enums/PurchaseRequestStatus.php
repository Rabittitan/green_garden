<?php

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Submitted => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Từ chối',
            self::Ordered => 'Đã đặt NCC',
            self::PartiallyReceived => 'Đã nhận một phần',
            self::Received => 'Đã nhận đủ',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Approved, self::Rejected, self::Draft],
            self::Approved => [self::Ordered, self::Cancelled],
            self::Ordered => [self::PartiallyReceived, self::Received, self::Cancelled],
            self::PartiallyReceived => [self::Received],
            self::Rejected, self::Received, self::Cancelled => [],
        }, true);
    }
}

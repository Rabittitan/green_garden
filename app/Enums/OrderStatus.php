<?php

namespace App\Enums;

/**
 * Trạng thái giao vận của đơn (khớp CHECK chk_o_status).
 *
 *  pending_payment ─► pending ─► confirmed ─► shipping ─► completed ─(trong hạn đổi trả)─► returning ─► returned
 *         └──────────────┴───────────┴─► cancelled           └────────────────────────────────────►┘
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipping = 'shipping';
    case Completed = 'completed';
    case Returning = 'returning';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Chờ thanh toán',
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Shipping => 'Đang giao',
            self::Completed => 'Hoàn thành',
            self::Returning => 'Đang hoàn về',
            self::Returned => 'Đã hoàn hàng',
            self::Cancelled => 'Đã hủy',
        };
    }

    /** Màu badge Bootstrap dùng ở view. */
    public function color(): string
    {
        return match ($this) {
            self::PendingPayment, self::Pending => 'warning',
            self::Confirmed, self::Shipping => 'info',
            self::Completed => 'success',
            self::Returning, self::Returned => 'secondary',
            self::Cancelled => 'danger',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Pending, self::Cancelled],
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Shipping, self::Cancelled],
            self::Shipping => [self::Completed, self::Returning],
            self::Completed => [self::Returning],
            self::Returning => [self::Returned],
            self::Returned, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** Khách được tự hủy khi shop chưa xác nhận. */
    public function isCustomerCancellable(): bool
    {
        return in_array($this, [self::PendingPayment, self::Pending], true);
    }

    /** Hàng còn trong kho (chưa giao): hủy thì hoàn kho ngay bằng sale_cancel. */
    public function isBeforeShipping(): bool
    {
        return in_array($this, [self::PendingPayment, self::Pending, self::Confirmed], true);
    }

    /** @return array<string,string> value => label, dùng cho bộ lọc ở view */
    public static function options(): array
    {
        return array_column(array_map(fn (self $s) => ['v' => $s->value, 'l' => $s->label()], self::cases()), 'l', 'v');
    }
}

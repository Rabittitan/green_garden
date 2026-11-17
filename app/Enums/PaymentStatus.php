<?php

namespace App\Enums;

/** Trạng thái tiền của đơn (khớp CHECK chk_o_ps_*). Luôn SUY RA từ bảng payments, không gán tay. */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case RefundPending = 'refund_pending';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chưa thanh toán',
            self::Paid => 'Đã thanh toán',
            self::RefundPending => 'Chờ hoàn tiền',
            self::PartiallyRefunded => 'Đã hoàn một phần',
            self::Refunded => 'Đã hoàn tiền',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'secondary',
            self::Paid => 'success',
            self::RefundPending => 'warning',
            self::PartiallyRefunded, self::Refunded => 'info',
        };
    }

    /**
     * Suy ra trạng thái từ số tiền đã thu / đã hoàn (đều tính từ payments succeeded).
     * Đơn 0đ còn hiệu lực coi như đã thanh toán (không có gì để thu); đơn 0đ đã đóng
     * (hủy / hoàn hàng) là "chưa thanh toán" vì DB cấm đơn đóng ở trạng thái paid.
     */
    public static function derive(int $total, int $paid, int $refunded, bool $hasPendingRefund, bool $orderClosed = false): self
    {
        return match (true) {
            $hasPendingRefund => self::RefundPending,
            $refunded > 0 && $refunded >= $paid => self::Refunded,
            $refunded > 0 => self::PartiallyRefunded,
            $paid > 0 => self::Paid,
            $total === 0 && ! $orderClosed => self::Paid,
            default => self::Unpaid,
        };
    }
}

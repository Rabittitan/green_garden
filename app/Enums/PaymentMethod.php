<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Vnpay = 'vnpay';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Thanh toán khi nhận hàng (COD)',
            self::Vnpay => 'VNPay (ATM / thẻ / QR)',
            self::BankTransfer => 'Chuyển khoản ngân hàng',
        };
    }

    /** Thu tiền TRƯỚC khi giao: đơn mới ở trạng thái pending_payment và có hạn giữ hàng. */
    public function isPrepaid(): bool
    {
        return $this !== self::Cod;
    }

    /** Số phút giữ hàng chờ thanh toán (config/shop.php). */
    public function holdMinutes(): ?int
    {
        return $this->isPrepaid() ? (int) config("shop.payment.hold_minutes.{$this->value}") : null;
    }
}

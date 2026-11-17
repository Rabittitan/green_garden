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

    /**
     * Phương thức có dùng được không (cấu hình đầy đủ). Checkout chỉ hiện và chỉ nhận các phương thức này.
     *  - VNPay: cổng thật có đủ khóa, hoặc cổng giả lập NHƯNG chỉ ở môi trường local/testing
     *  - Chuyển khoản: shop đã khai số tài khoản
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Cod => true,
            self::Vnpay => self::onlineGatewayMode() !== 'disabled',
            self::BankTransfer => trim((string) config('shop.bank_transfer.account_no')) !== '',
        };
    }

    /** @return list<self> */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m) => $m->isAvailable()));
    }

    /** 'vnpay' | 'fake' | 'disabled' */
    public static function onlineGatewayMode(): string
    {
        $mode = config('shop.payment.gateway');
        if ($mode === 'vnpay' && config('shop.vnpay.tmn_code') && config('shop.vnpay.hash_secret')) {
            return 'vnpay';
        }
        if ($mode === 'fake' && app()->environment(['local', 'testing'])) {
            return 'fake';
        }

        return 'disabled';
    }

    /** Số phút giữ hàng chờ thanh toán (config/shop.php). */
    public function holdMinutes(): ?int
    {
        return $this->isPrepaid() ? (int) config("shop.payment.hold_minutes.{$this->value}") : null;
    }
}

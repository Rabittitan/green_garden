<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessException;
use App\Models\Payment;

/**
 * Dùng khi thanh toán online CHƯA cấu hình đúng (thiếu khóa VNPay, hoặc để PAYMENT_GATEWAY=fake ngoài môi trường dev).
 * Mọi callback bị coi là sai chữ ký -> không ai giả được thanh toán; COD vẫn chạy bình thường.
 */
class DisabledGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'disabled';
    }

    /**
     * @return array<string, mixed>
     */
    public function initParams(Payment $payment, string $clientIp): array
    {
        throw new BusinessException('Thanh toán online chưa được cấu hình.');
    }

    public function paymentUrl(Payment $payment, string $returnUrl): string
    {
        throw new BusinessException('Thanh toán online chưa được cấu hình.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function parseCallback(array $query): GatewayResult
    {
        return new GatewayResult(false, $query['vnp_TxnRef'] ?? null, false, null, null, null, $query);
    }
}

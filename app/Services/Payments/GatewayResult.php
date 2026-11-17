<?php

namespace App\Services\Payments;

/** Kết quả đã parse (và đã kiểm chữ ký) từ callback của cổng thanh toán. */
final class GatewayResult
{
    public function __construct(
        public readonly bool $signatureValid,
        public readonly ?string $txnRef,
        public readonly bool $success,
        public readonly ?int $amount,            // VND
        public readonly ?string $providerTxnNo,
        public readonly ?string $responseCode,
        public readonly array $raw,
    ) {}
}

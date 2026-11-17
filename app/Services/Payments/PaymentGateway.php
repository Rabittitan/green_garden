<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function name(): string;

    /** Thông số cố định của 1 lần thanh toán (lưu vào payments.raw_payload) để dựng lại ĐÚNG link cũ khi khách quay lại trả tiếp. */
    public function initParams(Payment $payment, string $clientIp): array;

    public function paymentUrl(Payment $payment, string $returnUrl): string;

    /** Kiểm chữ ký + đọc kết quả từ query của IPN / return URL. */
    public function parseCallback(array $query): GatewayResult;
}

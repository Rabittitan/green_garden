<?php

namespace App\Services\Payments;

/**
 * Cổng GIẢ LẬP cho môi trường dev (VNPay không gọi IPN về localhost được).
 * Dùng đúng thuật toán ký của VNPay, khóa bí mật = APP_KEY, trang thanh toán là route nội bộ.
 * Nhờ vậy toàn bộ luồng IPN (chữ ký, số tiền, idempotent) chạy y như thật.
 */
class FakeGateway extends VnpayGateway
{
    public function __construct(string $secret, string $baseUrl)
    {
        parent::__construct('FAKE', $secret, $baseUrl);
    }

    public function name(): string
    {
        return 'fake';
    }
}

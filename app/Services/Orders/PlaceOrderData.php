<?php

namespace App\Services\Orders;

use App\Enums\PaymentMethod;

/** Dữ liệu đặt hàng đã qua validate ở FormRequest. */
final class PlaceOrderData
{
    /**
     * @param array<int,int> $cart plant_id => quantity
     * @param array{recipient_name:string, phone:string, province:string, district:string, ward:string, address_line:string} $shipping
     */
    public function __construct(
        public readonly array $cart,
        public readonly array $shipping,
        public readonly PaymentMethod $paymentMethod,
        public readonly string $checkoutToken,
        public readonly ?string $couponCode = null,
        public readonly ?int $expectedTotal = null,
        public readonly ?string $note = null,
        public readonly string $clientIp = '127.0.0.1',
    ) {}
}

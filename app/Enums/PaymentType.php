<?php

namespace App\Enums;

enum PaymentType: string
{
    case Charge = 'charge';
    case Refund = 'refund';

    public function label(): string
    {
        return $this === self::Charge ? 'Thu tiền' : 'Hoàn tiền';
    }
}

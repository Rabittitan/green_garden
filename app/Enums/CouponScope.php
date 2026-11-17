<?php

namespace App\Enums;

enum CouponScope: string
{
    case Order = 'order';
    case Plants = 'plants';
    case Categories = 'categories';
}

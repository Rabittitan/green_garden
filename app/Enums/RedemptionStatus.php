<?php

namespace App\Enums;

enum RedemptionStatus: string
{
    case Applied = 'applied';
    case Released = 'released';   // đơn bị hủy -> trả lượt dùng mã
}

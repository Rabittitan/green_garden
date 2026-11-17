<?php

namespace App\Enums;

enum TierChangeReason: string
{
    case Initial = 'initial';   // khi đăng ký
    case Upgrade = 'upgrade';   // tự động lên hạng khi tổng chi tiêu đạt ngưỡng
    case Manual = 'manual';     // admin chỉnh tay (bắt buộc có người chỉnh)

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Đăng ký',
            self::Upgrade => 'Lên hạng',
            self::Manual => 'Điều chỉnh thủ công',
        };
    }
}

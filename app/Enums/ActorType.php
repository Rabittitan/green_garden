<?php

namespace App\Enums;

/** Ai gây ra một lần đổi trạng thái đơn (order_status_histories.actor_type). */
enum ActorType: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case System = 'system';     // tác vụ nền: hết hạn thanh toán...
    case Gateway = 'gateway';   // cổng thanh toán (IPN)

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Khách hàng',
            self::Staff => 'Nhân viên',
            self::System => 'Hệ thống',
            self::Gateway => 'Cổng thanh toán',
        };
    }
}

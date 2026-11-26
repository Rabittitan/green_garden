<?php

namespace App\Enums;

/** Trạng thái của TỪNG chậu cây (bảng plant_units). */
enum PlantUnitStatus: string
{
    case InStock = 'in_stock';      // đang trên kệ (kể cả chậu đã có đơn đặt nhưng chưa quét giao)
    case Sold = 'sold';             // đã quét giao cho một dòng đơn
    case Damaged = 'damaged';       // xuất hủy: chết, dập, sâu bệnh... (phiếu báo lỗi / hàng hoàn bị hỏng)
    case Lost = 'lost';             // kiểm kê không thấy

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'Trong kho',
            self::Sold => 'Đã giao',
            self::Damaged => 'Đã hủy (lỗi)',
            self::Lost => 'Thất lạc',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::InStock => 'bg-success',
            self::Sold => 'bg-primary',
            self::Damaged => 'bg-danger',
            self::Lost => 'bg-warning text-dark',
        };
    }
}

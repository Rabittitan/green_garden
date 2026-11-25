<?php

namespace App\Enums;

/**
 * Nhóm quyền của NHÂN VIÊN trong khu quản trị (quản trị viên luôn có tất cả).
 * Việc về tiền / chính sách (hoàn tiền, xác nhận chuyển khoản, duyệt nhập hàng, mã giảm giá, hạng, khách hàng,
 * nhân viên) KHÔNG nằm ở đây: luôn chỉ dành cho quản trị viên (middleware admin:admin).
 */
enum StaffPermission: string
{
    case Reports = 'reports';
    case Orders = 'orders';
    case Catalog = 'catalog';
    case Inventory = 'inventory';
    case Purchasing = 'purchasing';
    case Reviews = 'reviews';
    case Content = 'content';
    case FlashSales = 'flash_sales';

    public function label(): string
    {
        return match ($this) {
            self::Reports => 'Thống kê',
            self::Orders => 'Đơn hàng',
            self::Catalog => 'Cây cảnh',
            self::Inventory => 'Kho (xem tồn, ghi cây chết)',
            self::Purchasing => 'Nhập hàng (yêu cầu nhập, phiếu nhập, nhà cung cấp)',
            self::Reviews => 'Đánh giá & hỏi đáp',
            self::Content => 'Tin tức & slide trang chủ',
            self::FlashSales => 'Flash sale (tạo, gửi duyệt)',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}

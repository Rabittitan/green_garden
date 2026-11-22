<?php

namespace App\Support;

use RuntimeException;

/**
 * Danh mục đơn vị hành chính sau sắp xếp 01/07/2025 (34 tỉnh/thành → xã/phường/đặc khu, không còn cấp huyện).
 * Một nguồn duy nhất: public/data/vn-dvhc-2025.json — trình duyệt tải để đổ danh sách chọn, server đọc để kiểm tra.
 * Nguồn gốc dữ liệu ghi trong khóa "_meta" của file.
 */
final class VnDivisions
{
    public const PATH = 'data/vn-dvhc-2025.json';

    /** @var array<string, list<string>>|null tỉnh => danh sách xã */
    private static ?array $map = null;

    /** @return array<string, list<string>> */
    public static function map(): array
    {
        if (self::$map === null) {
            $raw = @file_get_contents(public_path(self::PATH));
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (! is_array($data) || ! isset($data['provinces']) || ! is_array($data['provinces'])) {
                throw new RuntimeException('Thiếu hoặc hỏng file danh mục hành chính '.self::PATH);
            }
            $map = [];
            foreach ($data['provinces'] as $p) {
                $map[(string) $p['name']] = array_map('strval', $p['wards']);
            }
            self::$map = $map;
        }

        return self::$map;
    }

    /** @return list<string> tên tỉnh/thành theo thứ tự chữ cái */
    public static function provinces(): array
    {
        return array_keys(self::map());
    }

    /** @return list<string> xã/phường của tỉnh (rỗng nếu tỉnh không có trong danh mục) */
    public static function wards(?string $province): array
    {
        return self::map()[(string) $province] ?? [];
    }

    public static function hasWard(?string $province, ?string $ward): bool
    {
        return in_array((string) $ward, self::wards($province), true);
    }
}

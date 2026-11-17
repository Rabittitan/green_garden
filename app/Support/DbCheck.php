<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tiện ích ràng buộc tầng database cho migration.
 *
 * Dự án CHỈ hỗ trợ MySQL >= 8.0.16 hoặc MariaDB >= 10.4 vì schema dựa vào:
 *  - CHECK constraint (MySQL < 8.0.16 đọc cú pháp nhưng BỎ QUA, rất nguy hiểm)
 *  - cột sinh STORED có UNIQUE (chặn 2 giao dịch thanh toán thành công cho 1 đơn...)
 * Gặp engine khác thì dừng migrate ngay thay vì chạy âm thầm thiếu ràng buộc.
 */
class DbCheck
{
    public static function assertSupportedEngine(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("GreenLife chỉ hỗ trợ MySQL/MariaDB, đang dùng driver [{$driver}].");
        }

        $version = (string) DB::selectOne('SELECT VERSION() AS v')->v;
        $isMaria = stripos($version, 'mariadb') !== false;
        preg_match('/^(\d+\.\d+\.\d+)/', $version, $m);
        $min = $isMaria ? '10.4.0' : '8.0.16';

        if (! isset($m[1]) || version_compare($m[1], $min, '<')) {
            throw new RuntimeException('Cần '.($isMaria ? 'MariaDB' : 'MySQL')." >= {$min} (CHECK constraint), đang chạy {$version}.");
        }
    }

    public static function add(string $table, string $name, string $expression): void
    {
        DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
    }

    /**
     * Thêm nhiều CHECK cho một bảng trong MỘT câu ALTER (nhanh hơn, ít lần dựng lại bảng).
     *
     * @param  array<string,string>  $checks  tên => biểu thức
     */
    public static function addMany(string $table, array $checks): void
    {
        $parts = [];
        foreach ($checks as $name => $expression) {
            $parts[] = "ADD CONSTRAINT `{$name}` CHECK ({$expression})";
        }
        DB::statement("ALTER TABLE `{$table}` ".implode(', ', $parts));
    }

    /** Sinh biểu thức "col IN ('a','b')". Migration truyền mảng viết cứng, KHÔNG đọc từ Enum. */
    public static function in(string $column, array $values): string
    {
        $quoted = implode(',', array_map(fn ($v) => "'".str_replace("'", "''", $v)."'", $values));

        return "{$column} IN ({$quoted})";
    }
}

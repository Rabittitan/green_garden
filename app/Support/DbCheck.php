<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Thêm CHECK constraint ở tầng database.
 *
 * Schema Builder của Laravel không hỗ trợ CHECK, nên ta dùng câu SQL thô.
 * SQLite không cho thêm CHECK bằng ALTER TABLE, vì vậy khi chạy SQLite (môi trường dev)
 * hàm này bỏ qua. Lúc đó ràng buộc vẫn được đảm bảo bằng validation và Enum ở tầng ứng dụng.
 * Trên MySQL 8.0.16+, MariaDB 10.2+ và PostgreSQL, CHECK được áp dụng thật.
 */
class DbCheck
{
    public static function add(string $table, string $name, string $expression): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }

    public static function drop(string $table, string $name): void
    {
        $driver = DB::connection()->getDriverName();

        match ($driver) {
            'mysql' => DB::statement("ALTER TABLE {$table} DROP CHECK {$name}"),
            'mariadb', 'pgsql' => DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}"),
            default => null,
        };
    }

    /**
     * Sinh biểu thức "col IN ('a','b')".
     * Lưu ý: migration nên truyền mảng giá trị viết cứng, KHÔNG đọc từ Enum,
     * vì migration là lịch sử và không được đổi nghĩa khi Enum thay đổi về sau.
     */
    public static function in(string $column, array $values): string
    {
        $quoted = implode(',', array_map(fn ($v) => "'".str_replace("'", "''", $v)."'", $values));

        return "{$column} IN ({$quoted})";
    }
}

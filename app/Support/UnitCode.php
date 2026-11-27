<?php

namespace App\Support;

/**
 * Mã riêng của TỪNG chậu cây (in lên tem QR dán chậu).
 *
 * 8 ký tự Crockford base32 (bỏ I, L, O, U để không nhầm khi đọc / gõ tay): 32^8 ≈ 1,1 nghìn tỷ mã, sinh ngẫu nhiên,
 * DB giữ UNIQUE. Lưu không có gạch; in và hiển thị dạng "7KQ2-M9XA" cho dễ đọc.
 * normalize() nhận cả mã gõ tay: chữ thường, có gạch / khoảng trắng, gõ nhầm I/L thành 1, O thành 0.
 */
final class UnitCode
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const LENGTH = 8;

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        return $code;
    }

    /** Mã do máy quét / người gõ đưa vào -> dạng lưu trong DB, hoặc null nếu không phải mã chậu hợp lệ. */
    public static function normalize(string $input): ?string
    {
        $code = strtr(strtoupper(preg_replace('/[\s\-_.]+/', '', $input) ?? ''), ['I' => '1', 'L' => '1', 'O' => '0']);

        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH ? $code : null;
    }

    public static function format(string $code): string
    {
        return substr($code, 0, 4).'-'.substr($code, 4);
    }
}

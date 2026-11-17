<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Định dạng hiển thị (dùng qua Blade: @money($v), @vndt($date)). */
final class Format
{
    public static function money(int|float|string|null $value): string
    {
        return number_format((int) $value, 0, ',', '.').'đ';
    }

    /** DB lưu UTC; hiển thị theo giờ Việt Nam. */
    public static function datetime(?DateTimeInterface $value, string $format = 'd/m/Y H:i'): string
    {
        return $value ? Carbon::instance($value)->setTimezone(config('shop.display_timezone'))->format($format) : '';
    }
}

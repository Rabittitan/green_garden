<?php

namespace App\Support;

/**
 * Chia một số tiền nguyên theo tỷ lệ trọng số bằng phương pháp PHẦN DƯ LỚN NHẤT:
 * tổng các phần luôn ĐÚNG BẰNG $amount (không lệch 1đ) và không phần nào vượt trọng số của nó
 * khi $amount <= tổng trọng số.
 */
final class Allocator
{
    /**
     * @param  array<int|string,int>  $weights  key => trọng số (>= 0)
     * @return array<int|string,int> key => phần được chia (đủ mọi key, kể cả 0)
     */
    public static function allocate(int $amount, array $weights): array
    {
        $result = array_fill_keys(array_keys($weights), 0);
        $total = array_sum($weights);
        if ($amount <= 0 || $total <= 0) {
            return $result;
        }

        $remainders = [];
        foreach ($weights as $key => $w) {
            $exactNumerator = $amount * $w;                       // số nguyên, tránh sai số float
            $result[$key] = intdiv($exactNumerator, $total);
            $remainders[] = ['key' => $key, 'rem' => $exactNumerator % $total];
        }

        // phần dư lớn trước; bằng nhau thì theo thứ tự key ban đầu (ổn định, tái lập được)
        usort($remainders, fn ($a, $b) => $b['rem'] <=> $a['rem']);
        $left = $amount - array_sum($result);
        foreach ($remainders as $r) {
            if ($left <= 0) {
                break;
            }
            $result[$r['key']]++;
            $left--;
        }

        return $result;
    }
}

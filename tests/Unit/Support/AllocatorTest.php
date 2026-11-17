<?php

namespace Tests\Unit\Support;

use App\Support\Allocator;
use PHPUnit\Framework\TestCase;

class AllocatorTest extends TestCase
{
    public function test_sum_is_exact_and_largest_remainder_wins(): void
    {
        $this->assertSame(['a' => 34, 'b' => 33, 'c' => 33], Allocator::allocate(100, ['a' => 1, 'b' => 1, 'c' => 1]));
        $this->assertSame([0 => 0, 1 => 10], Allocator::allocate(10, [0, 5]));
        $this->assertSame([5 => 0, 9 => 0], Allocator::allocate(0, [5 => 3, 9 => 4]));
        $this->assertSame([1 => 0], Allocator::allocate(10, [1 => 0]));
    }

    public function test_random_allocations_are_exact_and_bounded(): void
    {
        mt_srand(20261117);
        for ($i = 0; $i < 2000; $i++) {
            $weights = [];
            for ($k = 0, $n = mt_rand(1, 6); $k < $n; $k++) {
                $weights[$k] = mt_rand(0, 5) === 0 ? 0 : mt_rand(1, 3_000_000);
            }
            $amount = mt_rand(0, array_sum($weights));
            $parts = Allocator::allocate($amount, $weights);

            $this->assertSame(array_sum($weights) > 0 ? $amount : 0, array_sum($parts));
            foreach ($parts as $k => $p) {
                $this->assertGreaterThanOrEqual(0, $p);
                $this->assertLessThanOrEqual($weights[$k], $p, 'không phần nào vượt trọng số');
            }
        }
    }
}

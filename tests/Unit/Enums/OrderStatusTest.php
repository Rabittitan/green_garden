<?php

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus as S;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    /** Ma trận đầy đủ: mọi cặp (từ, đến) không có trong bảng này đều phải bị từ chối. */
    private const ALLOWED = [
        'pending_payment' => ['pending', 'cancelled'],
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['completed', 'returning'],
        'completed' => ['returning'],
        'returning' => ['returned', 'completed'],
        'returned' => [],
        'cancelled' => [],
    ];

    public function test_every_transition_pair_matches_the_specification(): void
    {
        foreach (S::cases() as $from) {
            foreach (S::cases() as $to) {
                $expected = in_array($to->value, self::ALLOWED[$from->value], true);
                $this->assertSame($expected, $from->canTransitionTo($to), "{$from->value} -> {$to->value}");
            }
        }
    }

    public function test_final_and_cancellable_states(): void
    {
        $this->assertSame(['returned', 'cancelled'], array_values(array_map(fn ($s) => $s->value, array_filter(S::cases(), fn ($s) => $s->isFinal()))));
        $this->assertSame(['pending_payment', 'pending'], array_values(array_map(fn ($s) => $s->value, array_filter(S::cases(), fn ($s) => $s->isCustomerCancellable()))));
        // Mọi trạng thái được phép hủy đều là "chưa giao" => hoàn kho bằng sale_cancel là đúng
        foreach (S::cases() as $s) {
            if ($s->canTransitionTo(S::Cancelled)) {
                $this->assertTrue($s->isBeforeShipping(), $s->value);
            }
        }
    }

    public function test_every_state_has_label_and_color(): void
    {
        foreach (S::cases() as $s) {
            $this->assertNotSame('', $s->label());
            $this->assertNotSame('', $s->color());
        }
        $this->assertCount(8, S::options());
    }
}

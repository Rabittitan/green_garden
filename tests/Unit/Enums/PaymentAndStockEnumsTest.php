<?php

namespace Tests\Unit\Enums;

use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentAndStockEnumsTest extends TestCase
{
    public static function deriveCases(): array
    {
        // total, paid, refunded, pendingRefund => kết quả
        return [
            'chưa trả' => [280000, 0, 0, false, PaymentStatus::Unpaid],
            'đơn 0đ' => [0, 0, 0, false, PaymentStatus::Paid],
            'đã trả đủ' => [280000, 280000, 0, false, PaymentStatus::Paid],
            'chờ hoàn' => [280000, 280000, 0, true, PaymentStatus::RefundPending],
            'chờ hoàn tiếp sau khi đã hoàn 1 phần' => [280000, 280000, 100000, true, PaymentStatus::RefundPending],
            'hoàn 1 phần' => [280000, 280000, 100000, false, PaymentStatus::PartiallyRefunded],
            'hoàn hết' => [280000, 280000, 280000, false, PaymentStatus::Refunded],
        ];
    }

    #[DataProvider('deriveCases')]
    public function test_payment_status_is_derived_from_amounts(int $total, int $paid, int $refunded, bool $pending, PaymentStatus $expected): void
    {
        $this->assertSame($expected, PaymentStatus::derive($total, $paid, $refunded, $pending));
    }

    public function test_payment_state_machine(): void
    {
        $this->assertTrue(PaymentState::Pending->canTransitionTo(PaymentState::Succeeded));
        $this->assertTrue(PaymentState::Pending->canTransitionTo(PaymentState::Failed));
        $this->assertTrue(PaymentState::Pending->canTransitionTo(PaymentState::Cancelled));
        // IPN trễ: chỉ cổng thanh toán được lật cancelled -> succeeded
        $this->assertFalse(PaymentState::Cancelled->canTransitionTo(PaymentState::Succeeded));
        $this->assertTrue(PaymentState::Cancelled->canTransitionTo(PaymentState::Succeeded, confirmedByGateway: true));
        foreach (PaymentState::cases() as $to) {
            $this->assertFalse(PaymentState::Succeeded->canTransitionTo($to, true), "succeeded -> {$to->value}");
            $this->assertFalse(PaymentState::Failed->canTransitionTo($to, true), "failed -> {$to->value}");
        }
    }

    public function test_stock_movement_sign_rules(): void
    {
        $this->assertTrue(StockMovementType::Sale->acceptsQuantity(-2));
        $this->assertFalse(StockMovementType::Sale->acceptsQuantity(2));
        $this->assertTrue(StockMovementType::SaleReturn->acceptsQuantity(1));
        $this->assertFalse(StockMovementType::Damage->acceptsQuantity(1));
        $this->assertTrue(StockMovementType::Adjustment->acceptsQuantity(-5));
        $this->assertTrue(StockMovementType::Adjustment->acceptsQuantity(5));
        foreach (StockMovementType::cases() as $t) {
            $this->assertFalse($t->acceptsQuantity(0), $t->value);
        }
        $this->assertTrue(StockMovementType::SaleCancel->requiresOrderItem());
        $this->assertTrue(StockMovementType::PurchaseReceipt->requiresReceiptItem());
        $this->assertFalse(StockMovementType::Adjustment->requiresOrderItem());
    }
}

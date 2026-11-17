<?php

namespace Tests\Feature\Audit;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * H5 — Ma trận trạng thái đơn × hành động (tiêu chí C4).
 * Mỗi ô: hành động được phép thì chạy xong và mọi bất biến (kho, tiền, mã, hạng) vẫn đúng;
 * không được phép thì phải là lỗi NGHIỆP VỤ (không phải lỗi hệ thống) và KHÔNG để lại tác dụng phụ nào.
 */
class OrderStateMatrixTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    /** Hành động => các trạng thái được phép. */
    private const ALLOWED = [
        'confirm' => ['pending'],
        'ship' => ['confirmed'],
        'complete' => ['shipping'],
        'cancel_by_staff' => ['pending_payment', 'pending', 'confirmed'],
        'cancel_by_customer' => ['pending_payment', 'pending'],
        'cancel_by_system' => [],                                        // chưa quá hạn -> không ai bị hủy tự động
        'returning_by_staff' => ['shipping', 'completed'],
        'returning_by_customer' => ['completed'],
        'cancel_return' => ['returning_after_delivery'],
        'receive_return' => ['returning', 'returning_after_delivery'],
        'pay_online' => ['pending_payment'],
        'refund_partial' => ['pending', 'confirmed', 'shipping', 'completed'],   // đơn đã thu tiền (VNPay)
    ];

    private const STATES = ['pending_payment', 'pending', 'confirmed', 'shipping', 'completed',
        'returning', 'returning_after_delivery', 'returned', 'cancelled'];

    public static function matrix(): array
    {
        $cases = [];
        foreach (array_keys(self::ALLOWED) as $action) {
            foreach (self::STATES as $state) {
                $cases["{$action} @ {$state}"] = [$action, $state];
            }
        }

        return $cases;
    }

    /** Dựng 1 đơn VNPay (đã trả tiền, trừ pending_payment) ở đúng trạng thái yêu cầu. */
    private function orderIn(string $state): Order
    {
        $o = app(OrderService::class);
        $admin = $this->admin();
        $order = $this->place(method: PaymentMethod::Vnpay);
        if ($state === 'pending_payment') {
            return $order;
        }
        $this->ipn($order->payments()->sole());
        $order->refresh();

        match ($state) {
            'pending' => null,
            'cancelled' => $o->cancel($order, ActorType::Staff, $admin, 'Matrix'),
            default => $this->advance($order, $state),
        };

        return $order->refresh();
    }

    private function advance(Order $order, string $state): void
    {
        $o = app(OrderService::class);
        $admin = $this->admin();
        $o->confirm($order, $admin);
        if ($state === 'confirmed') {
            return;
        }
        $o->ship($order, 'GHN', 'M'.$order->id, $admin);
        if ($state === 'shipping') {
            return;
        }
        if ($state === 'returning' || $state === 'returned') {
            $o->markReturning($order, ActorType::Staff, $admin, 'Giao thất bại');
            if ($state === 'returned') {
                $item = $order->items()->sole();
                $o->receiveReturn($order, [$item->id => ['good' => 1, 'damaged' => 0]], $admin);
            }

            return;
        }
        $o->complete($order, $admin);
        if ($state === 'returning_after_delivery') {
            $o->markReturning($order, ActorType::Customer, $this->customer(), 'Cây héo');
        }
    }

    private function act(string $action, Order $order): void
    {
        $o = app(OrderService::class);
        $admin = $this->admin();
        $customer = $this->customer();
        match ($action) {
            'confirm' => $o->confirm($order, $admin),
            'ship' => $o->ship($order, 'GHTK', 'X'.$order->id, $admin),
            'complete' => $o->complete($order, $admin),
            'cancel_by_staff' => $o->cancel($order, ActorType::Staff, $admin, 'Thử'),
            'cancel_by_customer' => $o->cancel($order, ActorType::Customer, $customer, 'Thử'),
            'cancel_by_system' => $o->cancel($order, ActorType::System, null, 'Thử'),
            'returning_by_staff' => $o->markReturning($order, ActorType::Staff, $admin, 'Thử'),
            'returning_by_customer' => $o->markReturning($order, ActorType::Customer, $customer, 'Thử'),
            'cancel_return' => $o->cancelReturn($order, $admin, 'Thử'),
            'receive_return' => $o->receiveReturn($order, [$order->items()->sole()->id => ['good' => 1, 'damaged' => 0]], $admin),
            'pay_online' => app(PaymentService::class)->checkoutUrl($order, 'https://shop.test/r', '127.0.0.1'),
            'refund_partial' => app(PaymentService::class)->requestRefund($order, 10000, $admin, 'Thử'),
        };
    }

    #[DataProvider('matrix')]
    public function test_cell(string $action, string $state): void
    {
        $order = $this->orderIn($state);
        $before = [$order->status, $order->payment_status, $this->stock(1), Payment::count(), $order->histories()->count()];
        $allowed = in_array($state, self::ALLOWED[$action], true);

        try {
            $this->act($action, $order);
            $this->assertTrue($allowed, "Hành động {$action} lẽ ra phải bị CHẶN ở trạng thái {$state}");
        } catch (BusinessException $e) {
            $this->assertFalse($allowed, "Hành động {$action} lẽ ra được PHÉP ở trạng thái {$state}: ".$e->getMessage());
            $order->refresh();
            $this->assertSame($before, [$order->status, $order->payment_status, $this->stock(1), Payment::count(), $order->histories()->count()],
                'Bị chặn nhưng vẫn để lại tác dụng phụ');
        }

        $this->assertSame(0, Artisan::call('shop:reconcile'), "Bất biến bị phá sau {$action} @ {$state}:\n".Artisan::output());
    }

    public function test_every_state_is_reachable_and_every_non_final_state_has_an_exit(): void
    {
        foreach (OrderStatus::cases() as $s) {
            $exits = array_keys(array_filter(self::ALLOWED, fn ($states) => in_array($s->value, $states, true)
                || ($s === OrderStatus::Returning && in_array('returning_after_delivery', $states, true))));
            $this->assertSame($s->isFinal(), $exits === [] && $s !== OrderStatus::Returning,
                "Trạng thái {$s->value}: ".($s->isFinal() ? 'cuối nhưng vẫn có hành động' : 'không có lối ra'));
        }
        $this->assertSame(OrderStatus::Returned, $this->orderIn('returned')->status);
        $this->assertSame(OrderStatus::Cancelled, $this->orderIn('cancelled')->status);
    }
}

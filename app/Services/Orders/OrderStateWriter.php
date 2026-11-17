<?php

namespace App\Services\Orders;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\OrderStatusHistory;

/**
 * Đổi trạng thái đơn + ghi lịch sử. Đơn phải ĐÃ KHÓA DÒNG.
 * $attrs được ghi CÙNG câu UPDATE với status, để các CHECK liên cột (status ↔ payment_status,
 * cancelled_at, tracking_code...) không bao giờ thấy trạng thái nửa vời.
 */
class OrderStateWriter
{
    public function transition(Order $order, OrderStatus $to, ActorType $actor, ?int $by, ?string $note = null, array $attrs = []): Order
    {
        $from = $order->status;
        if (! $from->canTransitionTo($to)) {
            throw new BusinessException("Không thể chuyển đơn {$order->code} từ \"{$from->label()}\" sang \"{$to->label()}\".");
        }

        $order->update(array_merge($attrs, ['status' => $to]));
        $this->record($order, $from, $to, $actor, $by, $note);

        return $order;
    }

    public function record(Order $order, ?OrderStatus $from, OrderStatus $to, ActorType $actor, ?int $by, ?string $note = null): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id, 'from_status' => $from, 'to_status' => $to,
            'actor_type' => $actor, 'changed_by' => $by, 'note' => $note,
        ]);
    }
}

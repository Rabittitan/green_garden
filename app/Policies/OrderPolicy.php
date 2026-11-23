<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;

/** Quyền của KHÁCH trên đơn của chính mình (nhân viên đi qua khu admin). */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $order->status->isCustomerCancellable();
    }

    public function pay(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $order->status === OrderStatus::PendingPayment
            && $order->payment_method === PaymentMethod::Vnpay && ! $order->isPaymentExpired();
    }

    /** Thêm / sửa ghi chú cho shop: trước khi shop xác nhận đơn. */
    public function updateNote(User $user, Order $order): bool
    {
        return $this->view($user, $order) && in_array($order->status, OrderService::NOTE_EDITABLE, true);
    }

    public function requestReturn(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $order->isWithinReturnWindow();
    }
}

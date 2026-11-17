<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Đặt hàng và đổi trạng thái đơn, giữ đúng mọi bất biến của schema mới:
 *  - total = subtotal - discount_amount + shipping_fee
 *  - mỗi thay đổi tồn kho có 1 dòng stock_movements
 *  - mỗi lần đổi trạng thái có 1 dòng order_status_histories
 *  - lượt dùng mã giảm giá được khóa và đếm chính xác
 */
class OrderService
{
    public function __construct(
        private InventoryService $inventory,
        private CouponService $coupons,
    ) {}

    /**
     * @param array<int, array{quantity:int}> $cart  giỏ trong session: plant_id => [...]
     * @param array{customer_name:string, phone:string, address:string, note:?string} $shipping
     */
    public function place(User $user, array $cart, array $shipping, ?string $couponCode = null, int $shippingFee = 0): Order
    {
        if (! $cart) {
            throw new RuntimeException('Giỏ hàng đang trống!');
        }

        return DB::transaction(function () use ($user, $cart, $shipping, $couponCode, $shippingFee) {
            // Khóa các dòng cây theo thứ tự id tăng dần để 2 đơn song song không deadlock
            $ids = array_map('intval', array_keys($cart));
            sort($ids);
            $plants = Plant::whereIn('id', $ids)->where('is_active', true)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $lines = [];
            foreach ($ids as $id) {
                $plant = $plants->get($id);
                $qty = (int) $cart[$id]['quantity'];
                if (! $plant) {
                    throw new RuntimeException('Có cây trong giỏ đã ngừng bán, vui lòng kiểm tra lại giỏ hàng.');
                }
                if ($qty < 1 || $plant->stock < $qty) {
                    throw new RuntimeException("Cây \"{$plant->name}\" không đủ hàng trong kho!");
                }
                $lines[] = ['plant_id' => $id, 'category_id' => $plant->category_id, 'price' => $plant->price, 'quantity' => $qty];
            }

            $subtotal = array_sum(array_map(fn ($l) => $l['price'] * $l['quantity'], $lines));
            $quote = $couponCode ? $this->coupons->lockAndQuote($couponCode, $user, $lines, $shippingFee) : null;
            $itemDiscount = $quote['discount'] ?? 0;
            $shippingDiscount = $quote['shipping_discount'] ?? 0;
            $discountTotal = $itemDiscount + $shippingDiscount;

            $order = Order::create([
                'code' => Order::generateCode(),
                'user_id' => $user->id,
                'customer_name' => $shipping['customer_name'],
                'phone' => $shipping['phone'],
                'address' => $shipping['address'],
                'note' => $shipping['note'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'shipping_fee' => $shippingFee,
                'total' => $subtotal - $discountTotal + $shippingFee,
                'coupon_id' => $quote['coupon']->id ?? null,
                'coupon_code' => $quote['coupon']->code ?? null,
                'status' => OrderStatus::Pending->value,
            ]);

            foreach ($lines as $l) {
                $plant = $plants[$l['plant_id']];
                $order->items()->create([
                    'plant_id' => $plant->id,
                    'plant_name' => $plant->name,
                    'plant_sku' => $plant->sku,
                    'quantity' => $l['quantity'],
                    'price' => $l['price'],
                    'discount_amount' => $quote['allocations'][$plant->id] ?? 0,
                ]);
                $this->inventory->move($plant->id, StockMovementType::Sale, -$l['quantity'], $order);
            }

            if ($quote) {
                $this->coupons->redeem($quote['coupon'], $order, $discountTotal);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => null,
                'to_status' => OrderStatus::Pending->value, 'changed_by' => $user->id,
            ]);

            return $order;
        });
    }

    public function changeStatus(Order $order, OrderStatus $to, ?User $by, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $note) {
            // Khóa và đọc lại TRONG transaction: chặn bấm đúp / 2 admin cùng hủy -> cộng kho 2 lần
            $order = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            $from = OrderStatus::from($order->status);

            if (! $from->canTransitionTo($to)) {
                throw new RuntimeException("Không thể chuyển đơn từ \"{$from->label()}\" sang \"{$to->label()}\".");
            }

            if ($to === OrderStatus::Cancelled) {
                foreach ($order->items as $item) {
                    $this->inventory->move($item->plant_id, StockMovementType::SaleCancel, $item->quantity, $order);
                }
                $this->coupons->release($order);
            }

            $order->update([
                'status' => $to->value,
                'completed_at' => $to === OrderStatus::Completed ? now() : $order->completed_at,
                'cancelled_at' => $to === OrderStatus::Cancelled ? now() : $order->cancelled_at,
                'cancel_reason' => $to === OrderStatus::Cancelled ? $note : $order->cancel_reason,
                // COD: hoàn thành nghĩa là đã thu tiền
                'payment_status' => $to === OrderStatus::Completed && $order->payment_method === 'cod' ? 'paid' : $order->payment_status,
                'paid_at' => $to === OrderStatus::Completed && $order->payment_method === 'cod' ? now() : $order->paid_at,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => $from->value,
                'to_status' => $to->value, 'changed_by' => $by?->id, 'note' => $note,
            ]);

            return $order;
        });
    }
}

<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Exceptions\PriceChangedException;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderCompleted;
use App\Notifications\OrderShipped;
use App\Notifications\ReturnInstructions;
use App\Services\Orders\OrderStateWriter;
use App\Services\Orders\PlaceOrderData;
use App\Services\Pricing\Quote;
use App\Support\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Đặt hàng + vòng đời đơn.
 *
 * THỨ TỰ KHÓA (mọi luồng): users -> orders -> plants (id tăng dần) -> coupons -> payments.
 * Mọi transaction dùng attempts = 3 để tự thử lại khi MySQL báo deadlock.
 * Kết nối chạy READ COMMITTED (config/database.php) nên không có gap lock.
 */
class OrderService
{
    public function __construct(
        private PricingService $pricing,
        private CouponService $coupons,
        private InventoryService $inventory,
        private PaymentService $payments,
        private OrderStateWriter $state,
    ) {}

    // ================================================================ ĐẶT HÀNG

    public function place(User $user, PlaceOrderData $data): Order
    {
        if ($data->cart === []) {
            throw new BusinessException('Giỏ hàng đang trống!');
        }
        if (! $data->paymentMethod->isAvailable()) {
            throw new BusinessException('Phương thức thanh toán này hiện không khả dụng.');
        }

        try {
            return DB::transaction(function () use ($user, $data) {
                // 1. Khóa khách: tuần tự hóa các đơn của CÙNG 1 khách (chống bấm đúp, giới hạn đơn chờ, mã đơn đầu)
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                // 2. Bấm đúp / F5: token đã có đơn -> trả lại đơn đó
                if ($existing = $this->findByToken($user->id, $data->checkoutToken)) {
                    return $existing;
                }

                // 3. Giới hạn số đơn đang giữ hàng chờ thanh toán
                if ($data->paymentMethod->isPrepaid()) {
                    $pending = Order::where('user_id', $user->id)->status(OrderStatus::PendingPayment)->count();
                    if ($pending >= (int) config('shop.payment.max_pending_orders_per_user')) {
                        throw new BusinessException('Bạn đang có quá nhiều đơn chờ thanh toán. Vui lòng thanh toán hoặc hủy bớt trước khi đặt thêm.');
                    }
                }

                // 4. Khóa cây theo id tăng dần, kiểm tra còn bán + đủ hàng
                $plants = $this->inventory->lockPlants(array_keys($data->cart));
                $items = [];
                foreach ($data->cart as $plantId => $qty) {
                    $plant = $plants->get((int) $plantId);
                    if (! $plant || ! $plant->is_active || $plant->trashed()) {
                        throw new BusinessException('Có cây trong giỏ đã ngừng bán, vui lòng kiểm tra lại giỏ hàng.');
                    }
                    if ((int) $qty < 1) {
                        throw new BusinessException('Số lượng phải lớn hơn 0.');
                    }
                    if ($plant->stock < (int) $qty) {
                        throw new BusinessException("Cây \"{$plant->name}\" chỉ còn {$plant->stock} cây trong kho.");
                    }
                    $items[] = ['plant' => $plant, 'quantity' => (int) $qty];
                }

                // 5. Khóa mã + báo giá THẬT (cùng công thức với trang xem trước)
                $coupon = $data->couponCode !== null && trim($data->couponCode) !== ''
                    ? $this->coupons->find($data->couponCode, lock: true) : null;
                $quote = $this->pricing->quote($user, $items, $coupon);

                // 6. Khách phải đồng ý đúng con số sẽ trả
                if ($data->expectedTotal !== null && $quote->total() !== $data->expectedTotal) {
                    throw new PriceChangedException($quote);
                }

                // 7-11. Ghi đơn, dòng hàng, trừ kho, lượt mã, khoản thu, lịch sử
                $order = $this->createOrderRow($user, $data, $quote);
                foreach ($quote->lines as $line) {
                    $item = $order->items()->create([
                        'plant_id' => $line->plant->id, 'plant_name' => $line->plant->name, 'plant_sku' => $line->plant->sku,
                        'unit_price' => $line->unitPrice, 'quantity' => $line->quantity,
                        'member_discount' => $line->memberDiscount, 'coupon_discount' => $line->couponDiscount,
                        'unit_cost' => $line->plant->cost_price,
                    ]);
                    $this->inventory->move($line->plant, StockMovementType::Sale, -$line->quantity,
                        orderItem: $item, unitCost: $line->plant->cost_price, note: "Bán đơn {$order->code}", by: $user->id);
                }
                if ($quote->coupon) {
                    $this->coupons->redeem($quote->coupon, $order, $quote->couponBenefit());
                }
                if ($quote->total() > 0) {
                    $this->payments->openCharge($order, $data->clientIp);
                }
                $this->state->record($order, null, $order->status, ActorType::Customer, $user->id);

                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // Lưới an toàn: 2 request cùng token lọt qua (khóa user đã chặn gần như mọi trường hợp)
            if (str_contains($e->getMessage(), 'uq_orders_checkout') && ($existing = $this->findByToken($user->id, $data->checkoutToken))) {
                return $existing;
            }
            throw $e;
        }
    }

    private function findByToken(int $userId, string $token): ?Order
    {
        return Order::where('user_id', $userId)->where('checkout_token', $token)->first();
    }

    private function createOrderRow(User $user, PlaceOrderData $data, Quote $quote): Order
    {
        $prepaid = $data->paymentMethod->isPrepaid() && $quote->total() > 0;
        $status = $prepaid ? OrderStatus::PendingPayment : OrderStatus::Pending;
        $attrs = [
            'user_id' => $user->id, 'checkout_token' => $data->checkoutToken, 'status' => $status,
            'recipient_name' => $data->shipping['recipient_name'], 'phone' => $data->shipping['phone'],
            'province' => $data->shipping['province'], 'district' => $data->shipping['district'],
            'ward' => $data->shipping['ward'], 'address_line' => $data->shipping['address_line'], 'note' => $data->note,
            'membership_tier_id' => $quote->tier->id, 'member_discount_percent' => $quote->memberDiscountPercent,
            'coupon_id' => $quote->coupon?->id, 'coupon_code' => $quote->coupon?->code,
            'subtotal' => $quote->subtotal(), 'member_discount' => $quote->memberDiscount(), 'coupon_discount' => $quote->couponDiscount(),
            'shipping_fee' => $quote->shippingFee, 'shipping_discount' => $quote->shippingDiscount, 'total' => $quote->total(),
            'payment_method' => $data->paymentMethod,
            'payment_status' => PaymentStatus::derive($quote->total(), 0, 0, false),
            'payment_expires_at' => $prepaid ? now()->addMinutes($data->paymentMethod->holdMinutes()) : null,
            'placed_at' => now(),
        ];

        for ($attempt = 1; ; $attempt++) {
            try {
                return Order::create($attrs + ['code' => Order::newCode()]);
            } catch (UniqueConstraintViolationException $e) {
                // Trùng mã đơn ngẫu nhiên -> sinh mã khác (MySQL chỉ rollback câu lệnh lỗi, transaction vẫn tiếp tục)
                if ($attempt >= 5 || ! str_contains($e->getMessage(), 'orders_code_unique')) {
                    throw $e;
                }
            }
        }
    }

    // ================================================================ XỬ LÝ ĐƠN (admin)

    public function confirm(Order $order, User $by): Order
    {
        return $this->locked($order, fn (Order $o) => $this->state->transition($o, OrderStatus::Confirmed, ActorType::Staff, $by->id, null,
            ['confirmed_at' => now()]));
    }

    public function ship(Order $order, string $carrier, string $trackingCode, User $by): Order
    {
        if (trim($carrier) === '' || trim($trackingCode) === '') {
            throw new BusinessException('Phải nhập đơn vị vận chuyển và mã vận đơn.');
        }

        return $this->locked($order, function (Order $o) use ($carrier, $trackingCode, $by) {
            $this->state->transition($o, OrderStatus::Shipping, ActorType::Staff, $by->id, null,
                ['shipping_carrier' => trim($carrier), 'tracking_code' => trim($trackingCode), 'shipped_at' => now()]);
            Notifier::send($o->user, new OrderShipped($o));

            return $o;
        });
    }

    /** Giao thành công. COD: shipper đã thu tiền -> charge thành công. */
    public function complete(Order $order, User $by): Order
    {
        return $this->locked($order, function (Order $o) use ($by) {
            if ($o->status !== OrderStatus::Shipping) {                     // Returning -> Completed là "hủy trả hàng", không phải giao thành công
                throw new BusinessException("Đơn {$o->code} không ở trạng thái đang giao.");
            }
            $this->payments->settleCod($o);
            $o = $this->state->transition($o, OrderStatus::Completed, ActorType::Staff, $by->id, null,
                ['completed_at' => now()] + $this->payments->paymentAttributes($o, OrderStatus::Completed));
            Notifier::send($o->user, new OrderCompleted($o));                // COD: mốc thu tiền; mọi đơn: mốc bắt đầu hạn đổi trả

            return $o;
        });
    }

    // ================================================================ HỦY ĐƠN

    /**
     * Khách: khi đơn chờ thanh toán / chờ xác nhận và là đơn của chính họ.
     * Nhân viên: mọi trạng thái trước khi giao.  Hệ thống: chỉ đơn chờ thanh toán đã quá hạn.
     */
    public function cancel(Order $order, ActorType $actor, ?User $by, string $reason): Order
    {
        return $this->locked($order, function (Order $o) use ($actor, $by, $reason) {
            $this->authorizeCancel($o, $actor, $by);

            return $this->cancelLocked($o, $actor, $by?->id, $reason);
        });
    }

    private function authorizeCancel(Order $o, ActorType $actor, ?User $by): void
    {
        $allowed = match ($actor) {
            ActorType::Customer => $by !== null && $by->id === $o->user_id && $o->status->isCustomerCancellable(),
            ActorType::Staff => $by !== null && $by->isBackOffice() && $o->status->canTransitionTo(OrderStatus::Cancelled),
            ActorType::System => $o->isPaymentExpired(),
            ActorType::Gateway => false,
        };
        if (! $allowed) {
            throw new BusinessException("Không thể hủy đơn {$o->code} ở trạng thái \"{$o->status->label()}\".");
        }
    }

    /** Đơn đã khóa. Hoàn kho (giá vốn gốc), trả lượt mã, hủy khoản thu chờ, hoàn tiền nếu đã thu. */
    private function cancelLocked(Order $o, ActorType $actor, ?int $by, string $reason): Order
    {
        $items = $o->items()->get();
        $plants = $this->inventory->lockPlants($items->pluck('plant_id')->all());
        foreach ($items as $item) {
            $this->inventory->move($plants[$item->plant_id], StockMovementType::SaleCancel, $item->quantity,
                orderItem: $item, unitCost: $item->unit_cost, note: "Hủy đơn {$o->code}", by: $by);
        }
        $this->coupons->release($o);
        $this->payments->cancelPendingCharges($o);
        $refundable = $this->payments->refundableAmount($o);
        if ($refundable > 0) {
            $this->payments->createRefund($o, $refundable, $by, "Hủy đơn: {$reason}");
        }

        $this->state->transition($o, OrderStatus::Cancelled, $actor, $by, $reason,
            ['cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 255)] + $this->payments->paymentAttributes($o, OrderStatus::Cancelled));
        Notifier::send($o->user, new OrderCancelled($o));

        return $o;
    }

    /** Hủy các đơn chờ thanh toán đã quá hạn (lệnh chạy mỗi phút). Trả số đơn đã hủy. */
    public function expireOverdue(int $limit = 100): int
    {
        $ids = Order::status(OrderStatus::PendingPayment)
            ->where('payment_expires_at', '<', now()->subMinutes((int) config('shop.payment.expire_grace_minutes')))
            ->orderBy('id')->limit($limit)->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            try {
                $count += $this->expireIfOverdue(Order::findOrFail($id)) ? 1 : 0;
            } catch (Throwable $e) {
                report($e);                                                   // 1 đơn lỗi không chặn các đơn khác
            }
        }

        return $count;
    }

    /** Kiểm tra hạn NGAY lúc truy cập (không phụ thuộc scheduler). */
    public function expireIfOverdue(Order $order): bool
    {
        if (! $order->isPaymentExpired()) {
            return false;
        }

        return (bool) $this->locked($order, function (Order $o) {
            if (! $o->isPaymentExpired()) {
                return null;                                                  // IPN vừa thanh toán xong
            }

            return $this->cancelLocked($o, ActorType::System, null, 'Hết hạn thanh toán');
        });
    }

    // ================================================================ HOÀN HÀNG

    /** Bắt đầu hoàn: từ đang giao (giao thất bại / khách từ chối) hoặc đơn đã nhận còn trong hạn đổi trả. */
    public function markReturning(Order $order, ActorType $actor, ?User $by, string $reason): Order
    {
        return $this->locked($order, function (Order $o) use ($actor, $by, $reason) {
            $ok = match ($o->status) {
                OrderStatus::Shipping => $actor === ActorType::Staff,
                OrderStatus::Completed => $o->isWithinReturnWindow()
                    && ($actor === ActorType::Staff || ($actor === ActorType::Customer && $by?->id === $o->user_id)),
                default => false,
            };
            if (! $ok) {
                throw new BusinessException("Đơn {$o->code} không thể chuyển sang hoàn hàng (đã quá hạn đổi trả hoặc sai trạng thái).");
            }

            $wasDelivered = $o->status === OrderStatus::Completed;
            $this->state->transition($o, OrderStatus::Returning, $actor, $by?->id, $reason);
            if ($wasDelivered) {                                            // khách đang giữ cây: gửi hướng dẫn gửi trả
                Notifier::send($o->user, new ReturnInstructions($o));
            }

            return $o;
        });
    }

    /** Khách đổi ý không trả nữa (hàng chưa gửi về): đơn đã nhận quay lại "Hoàn thành". Chỉ áp cho đơn đi từ Hoàn thành. */
    public function cancelReturn(Order $order, User $by, string $note): Order
    {
        return $this->locked($order, function (Order $o) use ($by, $note) {
            if ($o->status !== OrderStatus::Returning || $o->completed_at === null) {
                throw new BusinessException("Đơn {$o->code} không có yêu cầu trả hàng nào có thể hủy (đơn giao thất bại phải nhận hàng hoàn về kho).");
            }

            return $this->state->transition($o, OrderStatus::Completed, ActorType::Staff, $by->id, "Hủy yêu cầu trả hàng: {$note}");
        });
    }

    /**
     * Nhận hàng hoàn về kho. Toàn bộ số lượng mỗi dòng phải được khai báo: tốt + hỏng = số đã bán.
     * Hàng tốt nhập lại kho; hàng hỏng ghi nhận rồi xuất hủy (sổ kho thấy đủ 2 bút toán).
     * Đã thu tiền -> tạo hoàn tiền (mặc định toàn bộ phần còn hoàn được; admin có thể nhập ít hơn, vd trừ phí ship khi lỗi do khách).
     *
     * @param  array<int, array{good:int, damaged:int}>  $quantities  order_item_id => số lượng
     */
    public function receiveReturn(Order $order, array $quantities, User $by, ?int $refundAmount = null, ?string $note = null): Order
    {
        return $this->locked($order, function (Order $o) use ($quantities, $by, $refundAmount, $note) {
            if ($o->status !== OrderStatus::Returning) {
                throw new BusinessException("Đơn {$o->code} không ở trạng thái đang hoàn về.");
            }
            $items = $o->items()->get();
            $plants = $this->inventory->lockPlants($items->pluck('plant_id')->all());

            foreach ($items as $item) {
                $good = (int) ($quantities[$item->id]['good'] ?? -1);
                $damaged = (int) ($quantities[$item->id]['damaged'] ?? -1);
                if ($good < 0 || $damaged < 0 || $good + $damaged !== $item->quantity) {
                    throw new BusinessException("Dòng \"{$item->plant_name}\": số tốt + số hỏng phải bằng {$item->quantity}.");
                }
                $plant = $plants[$item->plant_id];
                $this->inventory->move($plant, StockMovementType::SaleReturn, $item->quantity,
                    orderItem: $item, unitCost: $item->unit_cost, note: "Hoàn hàng đơn {$o->code}", by: $by->id);
                if ($damaged > 0) {
                    $this->inventory->move($plant, StockMovementType::Damage, -$damaged,
                        orderItem: $item, unitCost: $item->unit_cost, note: "Hàng hoàn bị hỏng - đơn {$o->code}", by: $by->id);
                }
                $item->update(['returned_good_qty' => $good, 'returned_damaged_qty' => $damaged]);
            }

            $this->payments->cancelPendingCharges($o);                       // COD chưa thu -> không phải thu nữa
            $refundable = $this->payments->refundableAmount($o);
            if ($refundable > 0) {
                $this->payments->createRefund($o, $refundAmount ?? $refundable, $by->id, 'Hoàn hàng'.($note ? ": {$note}" : ''));
            }

            return $this->state->transition($o, OrderStatus::Returned, ActorType::Staff, $by->id, $note,
                ['returned_at' => now()] + $this->payments->paymentAttributes($o, OrderStatus::Returned));
        });
    }

    // ================================================================ tiện ích

    /** Chạy $fn với đơn đã khóa dòng (đọc lại trong transaction: chặn bấm đúp / 2 admin cùng thao tác). */
    private function locked(Order $order, callable $fn): mixed
    {
        return DB::transaction(function () use ($order, $fn) {
            return $fn(Order::whereKey($order->id)->lockForUpdate()->firstOrFail());
        }, 3);
    }
}

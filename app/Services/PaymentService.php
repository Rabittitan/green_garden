<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Notifications\RefundCompleted;
use App\Services\Orders\OrderStateWriter;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;
use App\Support\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Thanh toán: thu (charge) / hoàn (refund), IPN, chuyển khoản, đồng bộ số tiền lên đơn.
 *
 * Thứ tự khóa: order -> payment. Số tiền trên đơn (paid_amount, refunded_amount, payment_status)
 * luôn được TÍNH LẠI từ bảng payments (paymentAttributes), không cộng trừ tay.
 *
 * Mỗi đơn chỉ có tối đa 1 charge đang chờ: khách bấm "thanh toán lại" sẽ nhận lại ĐÚNG link cũ
 * (cùng mã giao dịch) chứ không mở giao dịch mới, nên không thể bị trừ tiền 2 lần cho 1 đơn.
 */
class PaymentService
{
    public const IPN_OK = '00';

    public const IPN_NOT_FOUND = '01';

    public const IPN_ALREADY_DONE = '02';

    public const IPN_BAD_AMOUNT = '04';

    public const IPN_BAD_SIGNATURE = '97';

    public function __construct(
        private PaymentGateway $gateway,
        private OrderStateWriter $state,
        private MembershipService $membership,
    ) {}

    // ================================================================ thu tiền

    /** Mở charge cho đơn vừa tạo. Gọi trong transaction đặt hàng (đơn đã thuộc transaction). */
    public function openCharge(Order $order, string $clientIp): Payment
    {
        $payment = $order->payments()->create([
            'type' => PaymentType::Charge, 'method' => $order->payment_method, 'amount' => $order->total,
            'status' => PaymentState::Pending, 'txn_ref' => $this->nextTxnRef($order, PaymentType::Charge),
        ]);
        if ($order->payment_method === PaymentMethod::Vnpay) {
            $payment->setRelation('order', $order);
            $payment->update(['raw_payload' => ['init' => $this->gateway->initParams($payment, $clientIp)]]);
        }

        return $payment;
    }

    /** Link sang cổng thanh toán (lần đầu hoặc khách quay lại trả tiếp). */
    public function checkoutUrl(Order $order, string $returnUrl, string $clientIp): string
    {
        $charge = DB::transaction(function () use ($order, $clientIp) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_method !== PaymentMethod::Vnpay || $order->status !== OrderStatus::PendingPayment) {
                throw new BusinessException('Đơn hàng không ở trạng thái chờ thanh toán online.');
            }
            if ($order->isPaymentExpired()) {
                throw new BusinessException('Đơn hàng đã hết hạn thanh toán.');
            }
            $charge = Payment::where('order_id', $order->id)->where('type', PaymentType::Charge->value)
                ->where('status', PaymentState::Pending->value)->lockForUpdate()->first();

            return $charge ?? $this->openCharge($order, $clientIp);   // lần trước thất bại -> giao dịch mới
        }, 3);

        return $this->gateway->paymentUrl($charge->load('order'), $returnUrl);
    }

    /**
     * Xử lý IPN. Trả mã phản hồi cho cổng (00 = đã nhận). Mọi callback đều được ghi log thô trước.
     *
     * @param  array<string, mixed>  $query
     */
    public function handleCallback(array $query, ?string $ip = null): string
    {
        $result = $this->gateway->parseCallback($query);
        $log = PaymentWebhookLog::create([
            'provider' => $this->gateway->name(), 'txn_ref' => $result->txnRef !== null ? mb_substr($result->txnRef, 0, 40) : null,
            'payload' => $query, 'signature_valid' => $result->signatureValid, 'ip' => $ip,
        ]);

        try {
            $code = $this->processCallback($result);
        } catch (UniqueConstraintViolationException $e) {
            // Chỉ xảy ra nếu cổng báo thành công cho 1 giao dịch thứ hai của cùng đơn: tiền đã bị trừ 2 lần
            Log::critical('Đơn bị thanh toán 2 lần, cần hoàn tiền thủ công', ['txn_ref' => $result->txnRef, 'error' => $e->getMessage()]);
            $code = self::IPN_ALREADY_DONE;
        }
        $log->update(['result_code' => $code]);

        return $code;
    }

    private function processCallback(GatewayResult $r): string
    {
        if (! $r->signatureValid) {
            return self::IPN_BAD_SIGNATURE;
        }
        $found = $r->txnRef ? Payment::where('txn_ref', $r->txnRef)->first() : null;   // tìm KHÔNG khóa
        if (! $found || ! $found->isCharge()) {
            return self::IPN_NOT_FOUND;
        }

        return DB::transaction(function () use ($found, $r) {
            $order = Order::whereKey($found->order_id)->lockForUpdate()->firstOrFail();   // khóa order trước
            $payment = Payment::whereKey($found->id)->lockForUpdate()->firstOrFail();      // rồi mới tới payment

            if ($r->amount !== $payment->amount) {
                return self::IPN_BAD_AMOUNT;
            }
            if ($payment->status->isFinal()) {
                return self::IPN_ALREADY_DONE;                                           // idempotent
            }

            $payment->forceFill(['provider_txn_no' => $r->providerTxnNo, 'provider_response_code' => $r->responseCode,
                'raw_payload' => array_merge($payment->raw_payload ?? [], ['ipn' => $r->raw])]);

            if (! $r->success) {
                if ($payment->status === PaymentState::Pending) {
                    $payment->status = PaymentState::Failed;                             // đơn vẫn chờ, khách trả lại được
                }
                $payment->save();

                return self::IPN_OK;
            }

            if (! $payment->status->canTransitionTo(PaymentState::Succeeded, confirmedByGateway: true)) {
                return self::IPN_ALREADY_DONE;
            }
            $this->applySucceededCharge($order, $payment, ActorType::Gateway, null);

            return self::IPN_OK;
        }, 3);
    }

    /** Admin xác nhận đã nhận chuyển khoản (số tiền phải khớp tuyệt đối). */
    public function confirmBankTransfer(Order $order, int $amount, string $bankRef, User $by): Order
    {
        return DB::transaction(function () use ($order, $amount, $bankRef, $by) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_method !== PaymentMethod::BankTransfer) {
                throw new BusinessException('Đơn không thanh toán bằng chuyển khoản.');
            }
            $charge = Payment::where('order_id', $order->id)->where('type', PaymentType::Charge->value)
                ->whereIn('status', [PaymentState::Pending->value, PaymentState::Cancelled->value])
                ->orderByDesc('id')->lockForUpdate()->first();
            if (! $charge) {
                throw new BusinessException('Không có khoản thu nào đang chờ xác nhận.');
            }
            if ($amount !== $charge->amount) {
                throw new BusinessException('Số tiền nhận được ('.number_format($amount, 0, ',', '.').'đ) không khớp số phải thu ('
                    .number_format($charge->amount, 0, ',', '.').'đ). Hãy liên hệ khách để xử lý.');
            }
            $charge->forceFill(['provider_txn_no' => $bankRef, 'note' => 'Xác nhận bởi '.$by->name]);
            $this->applySucceededCharge($order, $charge, ActorType::Staff, $by->id);

            return $order;
        }, 3);
    }

    /** Charge thành công (đơn + payment đã khóa). */
    private function applySucceededCharge(Order $order, Payment $charge, ActorType $actor, ?int $by): void
    {
        $charge->forceFill(['status' => PaymentState::Succeeded, 'paid_at' => now()])->save();

        if ($order->status === OrderStatus::PendingPayment) {
            $this->state->transition($order, OrderStatus::Pending, $actor, $by, 'Đã thanh toán',
                $this->paymentAttributes($order, OrderStatus::Pending));
            Notifier::send($order->user, new PaymentReceived($order));
        } elseif ($order->status === OrderStatus::Cancelled) {
            // Tiền về SAU khi đơn đã hủy (hết hạn): không mở lại đơn (hàng có thể đã bán), hoàn tiền cho khách
            $this->createRefund($order, $charge->amount, $by, 'Thanh toán đến sau khi đơn đã hủy - hoàn tiền tự động');
            $this->recalc($order);
            Log::warning('Thanh toán đến sau khi đơn đã hủy', ['order' => $order->code, 'txn_ref' => $charge->txn_ref]);
        } else {
            $this->recalc($order);
        }
    }

    /** Hoàn tất thu COD khi giao thành công (đơn đã khóa). */
    public function settleCod(Order $order): void
    {
        $charge = Payment::where('order_id', $order->id)->where('type', PaymentType::Charge->value)
            ->where('status', PaymentState::Pending->value)->lockForUpdate()->first();
        if ($charge && $charge->method === PaymentMethod::Cod) {
            $charge->forceFill(['status' => PaymentState::Succeeded, 'paid_at' => now()])->save();
        }
    }

    /** Hủy các charge đang chờ (đơn đã khóa). */
    public function cancelPendingCharges(Order $order): void
    {
        Payment::where('order_id', $order->id)->where('type', PaymentType::Charge->value)
            ->where('status', PaymentState::Pending->value)->lockForUpdate()->get()
            ->each(fn (Payment $p) => $p->forceFill(['status' => PaymentState::Cancelled])->save());
    }

    // ================================================================ hoàn tiền

    /** Số tiền còn có thể hoàn = đã thu − đã hoàn − đang chờ hoàn. */
    public function refundableAmount(Order $order): int
    {
        $pendingRefunds = (int) Payment::where('order_id', $order->id)->where('type', PaymentType::Refund->value)
            ->where('status', PaymentState::Pending->value)->sum('amount');

        return $this->sumSucceeded($order, PaymentType::Charge) - $this->sumSucceeded($order, PaymentType::Refund) - $pendingRefunds;
    }

    /** Admin tạo yêu cầu hoàn tiền (vd khiếu nại cây chết sau khi nhận). */
    public function requestRefund(Order $order, int $amount, User $by, string $note): Payment
    {
        return DB::transaction(function () use ($order, $amount, $by, $note) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === OrderStatus::Returning) {
                // Hàng đang trên đường về, chưa kiểm được tình trạng: hoàn tiền tại bước "Nhận hàng hoàn về kho"
                throw new BusinessException('Đơn đang hoàn về: hãy hoàn tiền khi nhận hàng hoàn về kho.');
            }
            $refund = $this->createRefund($order, $amount, $by->id, $note);
            $this->recalc($order);

            return $refund;
        }, 3);
    }

    /** Tạo refund pending (đơn đã khóa). */
    public function createRefund(Order $order, int $amount, ?int $by, string $note): Payment
    {
        $refundable = $this->refundableAmount($order);
        if ($amount < 1 || $amount > $refundable) {
            throw new BusinessException('Số tiền hoàn phải từ 1đ đến '.number_format($refundable, 0, ',', '.').'đ.');
        }
        $charge = Payment::where('order_id', $order->id)->where('type', PaymentType::Charge->value)
            ->where('status', PaymentState::Succeeded->value)->firstOrFail();

        return $order->payments()->create([
            'parent_payment_id' => $charge->id, 'type' => PaymentType::Refund, 'method' => $charge->method,
            'amount' => $amount, 'status' => PaymentState::Pending, 'txn_ref' => $this->nextTxnRef($order, PaymentType::Refund),
            'created_by' => $by, 'note' => mb_substr($note, 0, 255),
        ]);
    }

    /** Admin xác nhận đã hoàn tiền xong (qua cổng / chuyển khoản), kèm mã giao dịch. */
    public function confirmRefund(Payment $refund, User $by, string $providerRef): Payment
    {
        $order = DB::transaction(function () use ($refund, $by, $providerRef) {
            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $refund = Payment::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->type !== PaymentType::Refund || $refund->status !== PaymentState::Pending) {
                throw new BusinessException('Khoản hoàn tiền không ở trạng thái chờ.');
            }
            $refund->forceFill(['status' => PaymentState::Succeeded, 'paid_at' => now(), 'provider_txn_no' => $providerRef,
                'note' => trim(($refund->note ?? '').' | Xác nhận bởi '.$by->name)])->save();
            $this->recalc($order);
            Notifier::send($order->user, new RefundCompleted($order, $refund));

            return $order;
        }, 3);

        // Đơn đã tính vào tổng chi tiêu -> tính lại (transaction riêng, khóa users trước)
        if ($order->spend_recorded_at !== null) {
            $this->membership->refreshUser($order->user_id);
        }

        return $refund->refresh();
    }

    // ================================================================ đồng bộ số tiền lên đơn

    /**
     * Thuộc tính tiền của đơn, tính từ payments, cho trạng thái đơn $status (dùng chung câu UPDATE với đổi trạng thái).
     *
     * @return array<string, mixed>
     */
    public function paymentAttributes(Order $order, OrderStatus $status): array
    {
        $paid = $this->sumSucceeded($order, PaymentType::Charge);
        $refunded = $this->sumSucceeded($order, PaymentType::Refund);
        $pendingRefund = Payment::where('order_id', $order->id)->where('type', PaymentType::Refund->value)
            ->where('status', PaymentState::Pending->value)->exists();
        if ($paid > $order->total) {
            throw new LogicException("Đơn {$order->code} thu vượt tổng tiền.");
        }
        $closed = in_array($status, [OrderStatus::Cancelled, OrderStatus::Returned], true);

        return [
            'paid_amount' => $paid,
            'refunded_amount' => $refunded,
            'payment_status' => PaymentStatus::derive($order->total, $paid, $refunded, $pendingRefund, $closed),
            'paid_at' => $order->paid_at ?? ($paid > 0 ? now() : null),
        ];
    }

    public function recalc(Order $order): void
    {
        $order->update($this->paymentAttributes($order, $order->status));
    }

    private function sumSucceeded(Order $order, PaymentType $type): int
    {
        return (int) Payment::where('order_id', $order->id)->where('type', $type->value)
            ->where('status', PaymentState::Succeeded->value)->sum('amount');
    }

    private function nextTxnRef(Order $order, PaymentType $type): string
    {
        $n = Payment::where('order_id', $order->id)->where('type', $type->value)->count() + 1;

        return ($type === PaymentType::Refund ? 'RF' : '').$order->code.'-'.$n;
    }
}

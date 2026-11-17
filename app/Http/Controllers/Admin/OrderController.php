<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Xử lý đơn: mỗi nút chỉ hiện khi máy trạng thái cho phép; mọi nghiệp vụ nằm ở Service. */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private PaymentService $payments) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'q' => ['nullable', 'string', 'max:50'],
        ]);
        $orders = Order::with('user')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('code', 'like', "%{$v}%")
                ->orWhere('phone', 'like', "%{$v}%")->orWhere('recipient_name', 'like', "%{$v}%")))
            ->latest('id')->paginate(15)->withQueryString();

        $counts = Order::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return view('admin.orders.index', compact('orders', 'counts'));
    }

    public function show(Order $order): View
    {
        $this->orders->expireIfOverdue($order);
        $order->refresh()->load(['user.tier', 'items.plant', 'payments.creator', 'histories.changedBy', 'redemption']);

        return view('admin.orders.show', [
            'order' => $order,
            'refundable' => $this->payments->refundableAmount($order),
            'pendingRefunds' => $order->payments->where('type', PaymentType::Refund)->where('status', PaymentState::Pending),
        ]);
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        return $this->run(fn () => $this->orders->confirm($order, $request->user()), 'Đã xác nhận đơn.');
    }

    public function ship(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['shipping_carrier' => ['required', 'string', 'max:30'], 'tracking_code' => ['required', 'string', 'max:50']],
            [], ['shipping_carrier' => 'đơn vị vận chuyển', 'tracking_code' => 'mã vận đơn']);

        return $this->run(fn () => $this->orders->ship($order, $data['shipping_carrier'], $data['tracking_code'], $request->user()), 'Đã giao cho đơn vị vận chuyển.');
    }

    public function complete(Request $request, Order $order): RedirectResponse
    {
        return $this->run(fn () => $this->orders->complete($order, $request->user()), 'Đơn đã hoàn thành.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'lý do hủy']);

        return $this->run(fn () => $this->orders->cancel($order, ActorType::Staff, $request->user(), $data['reason']), 'Đã hủy đơn, hàng đã được trả về kho.');
    }

    public function returning(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'lý do hoàn hàng']);

        return $this->run(fn () => $this->orders->markReturning($order, ActorType::Staff, $request->user(), $data['reason']), 'Đơn chuyển sang đang hoàn về.');
    }

    public function cancelReturn(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], [], ['note' => 'ghi chú']);

        return $this->run(fn () => $this->orders->cancelReturn($order, $request->user(), $data['note']), 'Đã hủy yêu cầu trả hàng, đơn trở lại Hoàn thành.');
    }

    public function receiveReturn(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.good' => ['required', 'integer', 'min:0'],
            'items.*.damaged' => ['required', 'integer', 'min:0'],
            'refund_amount' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->run(fn () => $this->orders->receiveReturn($order, $data['items'], $request->user(),
            isset($data['refund_amount']) ? (int) $data['refund_amount'] : null, $data['note'] ?? null), 'Đã nhận hàng hoàn về kho.');
    }

    public function confirmTransfer(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1'], 'bank_ref' => ['required', 'string', 'max:60']],
            [], ['amount' => 'số tiền nhận được', 'bank_ref' => 'mã giao dịch ngân hàng']);

        return $this->run(fn () => $this->payments->confirmBankTransfer($order, (int) $data['amount'], $data['bank_ref'], $request->user()), 'Đã xác nhận nhận tiền chuyển khoản.');
    }

    public function requestRefund(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1'], 'note' => ['required', 'string', 'max:200']],
            [], ['amount' => 'số tiền hoàn', 'note' => 'lý do hoàn tiền']);

        return $this->run(fn () => $this->payments->requestRefund($order, (int) $data['amount'], $request->user(), $data['note']), 'Đã tạo yêu cầu hoàn tiền.');
    }

    public function confirmRefund(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['provider_ref' => ['required', 'string', 'max:60']], [], ['provider_ref' => 'mã giao dịch hoàn tiền']);

        return $this->run(fn () => $this->payments->confirmRefund($payment, $request->user(), $data['provider_ref']), 'Đã xác nhận hoàn tiền cho khách.');
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', $success);
    }
}

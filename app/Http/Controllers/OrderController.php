<?php

namespace App\Http\Controllers;

use App\Enums\ActorType;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Đơn hàng của khách: lịch sử, chi tiết, thanh toán lại, hủy, yêu cầu trả hàng. */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->latest('id')->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);
        $this->orders->expireIfOverdue($order);                         // hết hạn thì hủy ngay, không chờ lệnh định kỳ
        $order->refresh()->load(['items.plant', 'payments', 'histories', 'tier']);

        return view('orders.show', ['order' => $order, 'bank' => config('shop.bank_transfer')]);
    }

    /** Sang cổng thanh toán (lần đầu hoặc trả tiếp: cùng một giao dịch đang chờ). */
    public function pay(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('view', $order);
        if ($this->orders->expireIfOverdue($order) || $request->user()->cannot('pay', $order->refresh())) {
            return redirect()->route('orders.show', $order)->with('error', 'Đơn hàng không còn ở trạng thái chờ thanh toán.');
        }
        try {
            $url = $payments->checkoutUrl($order, route('payment.vnpay.return'), (string) $request->ip());
        } catch (BusinessException $e) {
            return redirect()->route('orders.show', $order)->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        try {
            $this->orders->cancel($order, ActorType::Customer, $request->user(), $data['reason']);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('success', 'Đã hủy đơn hàng.');
    }

    public function requestReturn(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('requestReturn', $order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        try {
            $this->orders->markReturning($order, ActorType::Customer, $request->user(), $data['reason']);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('success', 'Đã gửi yêu cầu trả hàng. Shop sẽ liên hệ để nhận lại cây.');
    }
}

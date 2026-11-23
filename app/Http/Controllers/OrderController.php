<?php

namespace App\Http\Controllers;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\PlantReview;
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

        // Đơn đã nhận hàng: mỗi cây có nút đánh giá; 1 truy vấn lấy đánh giá sẵn có của khách cho các cây trong đơn
        $myReviews = $order->status === OrderStatus::Completed
            ? PlantReview::where('user_id', $order->user_id)->whereIn('plant_id', $order->items->pluck('plant_id'))->get()->keyBy('plant_id')
            : collect();

        return view('orders.show', ['order' => $order, 'bank' => config('shop.bank_transfer'), 'myReviews' => $myReviews]);
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

    public function updateNote(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']], [], ['note' => 'ghi chú']);
        try {
            $this->orders->updateNote($order, $request->user(), $data['note'] ?? null);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('success', 'Đã lưu ghi chú cho shop.');
    }

    /** Hóa đơn bán lẻ để in / lưu PDF (chỉ đọc: không chạy hủy đơn quá hạn như trang chi tiết). */
    public function invoice(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('invoices.order', ['order' => $order->load(['items', 'tier']), 'backUrl' => route('orders.show', $order)]);
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

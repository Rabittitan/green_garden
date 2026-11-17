<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class CheckoutController extends Controller
{
    // Hiển thị form đặt hàng
    public function form()
    {
        $cart = session('cart', []);                           // lấy giỏ hàng từ session
        if (empty($cart)) {                                    // nếu giỏ trống
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng đang trống!'); // đẩy về trang giỏ
        }
        $total = 0;                                            // khởi tạo tổng tiền
        foreach ($cart as $item) {                             // duyệt giỏ
            $total += $item['price'] * $item['quantity'];      // cộng dồn tiền
        }
        return view('checkout.form', compact('cart', 'total')); // hiện form đặt hàng
    }

    // Xử lý đặt hàng: toàn bộ nghiệp vụ nằm trong OrderService
    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'customer_name' => 'required|max:100',
            'phone'         => ['required', 'regex:/^(0|\+84)[0-9]{9,10}$/'],
            'address'       => 'required|max:255',
            'note'          => 'nullable|max:1000',
            'coupon_code'   => 'nullable|string|max:40',
        ]);

        try {
            $order = $orders->place(Auth::user(), session('cart', []), $data, $data['coupon_code'] ?? null);
        } catch (RuntimeException $e) {                        // lỗi nghiệp vụ: hết hàng, mã sai... -> hiện cho khách
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        } catch (\Throwable $e) {                              // lỗi hệ thống: ghi log, KHÔNG lộ chi tiết ra ngoài
            report($e);
            return redirect()->route('cart.index')->with('error', 'Có lỗi xảy ra, vui lòng thử lại sau.');
        }

        session()->forget('cart');

        // Đơn đã lưu xong (transaction đã commit) mới phát event -> Listener gửi mail xác nhận.
        // Lỗi gửi mail KHÔNG được làm khách tưởng đặt hàng thất bại.
        try {
            OrderPlaced::dispatch($order);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('orders.show', $order->id)->with('success', 'Đặt hàng thành công! Mã đơn: '.$order->code.'. Email xác nhận đã được gửi tới '.Auth::user()->email.'.');
    }

    // Lịch sử đơn hàng của khách đang đăng nhập
    public function history()
    {
        $orders = Order::where('user_id', Auth::id())          // chỉ lấy đơn của chính khách này
            ->latest()->paginate(10);                          // mới nhất trước, 10 đơn/trang
        return view('orders.history', compact('orders'));
    }

    // Chi tiết 1 đơn hàng
    public function show($id)
    {
        $order = Order::where('user_id', Auth::id())           // chỉ cho xem đơn của chính mình (chống xem trộm đơn người khác)
            ->with('items.plant')                              // nạp luôn chi tiết đơn và thông tin cây
            ->findOrFail($id);                                 // không thấy thì 404
        return view('orders.show', compact('order'));
    }
}
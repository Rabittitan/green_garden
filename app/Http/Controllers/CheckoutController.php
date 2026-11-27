<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;
use App\Exceptions\CouponNotApplicableException;
use App\Exceptions\PriceChangedException;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\Coupon;
use App\Notifications\OrderReceived;
use App\Services\AddressService;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PricingService;
use App\Support\Format;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Xem lại đơn (báo giá) -> áp mã -> đặt hàng.
 * Con số khách thấy (expected_total) được gửi kèm khi đặt; giá đổi thì không tạo đơn.
 */
class CheckoutController extends Controller
{
    private const COUPON_KEY = 'checkout.coupon';

    private const TOKEN_KEY = 'checkout.token';

    public function __construct(private CartService $cart, private PricingService $pricing) {}

    public function form(Request $request): RedirectResponse|View
    {
        foreach ($this->cart->reconcile() as $notice) {
            session()->now('warning', $notice);
        }
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng đang trống!');
        }

        $user = $request->user()->load('tier');
        $couponCode = session(self::COUPON_KEY);
        // Phí ship theo vùng: tính theo tỉnh đang điền sẵn (vừa nhập lỗi / địa chỉ mặc định); đổi tỉnh thì JS gọi shippingQuote
        $addresses = $user->addresses()->get();
        $default = $addresses->firstWhere('is_default', true) ?? $addresses->first();
        $province = old('province', $default?->province);
        try {
            $quote = $this->pricing->quoteCart($user, $this->cart->items(), $couponCode, $province);
        } catch (CouponNotApplicableException $e) {
            session()->forget(self::COUPON_KEY);                     // mã hết hiệu lực -> bỏ mã, báo khách
            session()->now('error', $e->getMessage().' Mã đã được gỡ khỏi đơn.');
            $quote = $this->pricing->quoteCart($user, $this->cart->items(), null, $province);
        } catch (BusinessException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        if (! session()->has(self::TOKEN_KEY)) {
            session([self::TOKEN_KEY => (string) Str::uuid()]);
        }

        return view('checkout.form', [
            'quote' => $quote,
            'user' => $user,
            'addresses' => $addresses,
            'methods' => PaymentMethod::available(),
            'token' => session(self::TOKEN_KEY),
            'nextTier' => $user->tier->next(),
            // "Mã của tôi" còn dùng được: bấm để áp nhanh (hạn gần nhất trước)
            'myCoupons' => Coupon::walletUsable($user->id)->orderBy('ends_at')->take(5)->get(),
        ]);
    }

    /**
     * Trang đặt hàng đổi tỉnh nhận hàng -> tính lại phí ship + tổng (chỉ đọc). JS cập nhật số hiển thị và expected_total;
     * không có JS thì lúc đặt hàng server vẫn tính lại và báo "giá đã thay đổi" nếu khác.
     */
    public function shippingQuote(Request $request): JsonResponse
    {
        $province = $request->validate(['province' => ['nullable', 'string', 'max:100']])['province'] ?? null;
        try {
            $quote = $this->pricing->quoteCart($request->user()->load('tier'), $this->cart->items(), session(self::COUPON_KEY), $province);
        } catch (BusinessException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'shipping_fee' => $quote->shippingFee, 'shipping_discount' => $quote->shippingDiscount, 'total' => $quote->total(),
            'shipping_fee_text' => $quote->shippingFee > 0 ? Format::money($quote->shippingFee) : 'Miễn phí',
            'total_text' => Format::money($quote->total()),
        ]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['coupon_code' => ['required', 'string', 'max:40']]);
        try {
            $quote = $this->pricing->quoteCart($request->user(), $this->cart->items(), $data['coupon_code']);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
        if ($quote->coupon === null) {                                // mã không cộng dồn, ưu đãi hạng lợi hơn
            return back()->with('warning', $quote->notices[0] ?? 'Mã không được áp dụng.');
        }
        session([self::COUPON_KEY => $quote->coupon->code]);

        return back()->with('success', "Đã áp dụng mã {$quote->coupon->code}: giảm ".Format::money($quote->couponBenefit()).'.');
    }

    public function removeCoupon(): RedirectResponse
    {
        session()->forget(self::COUPON_KEY);

        return back()->with('success', 'Đã bỏ mã giảm giá.');
    }

    public function store(PlaceOrderRequest $request, OrderService $orders, PaymentService $payments): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng đang trống!');
        }
        $user = $request->user();

        try {
            $order = $orders->place($user, $request->toData($this->cart->items(), session(self::COUPON_KEY)));
        } catch (PriceChangedException $e) {
            return redirect()->route('checkout.form')->with('error', $e->getMessage())->withInput();
        } catch (CouponNotApplicableException $e) {
            session()->forget(self::COUPON_KEY);

            return redirect()->route('checkout.form')->with('error', $e->getMessage().' Mã đã được gỡ, vui lòng kiểm tra lại đơn.')->withInput();
        } catch (BusinessException $e) {
            return redirect()->route('checkout.form')->with('error', $e->getMessage())->withInput();
        }

        $isNew = $order->wasRecentlyCreated;
        $this->cart->clear();
        session()->forget([self::COUPON_KEY, self::TOKEN_KEY]);

        if ($isNew) {
            if ($request->boolean('save_address') || ! $user->addresses()->exists()) {
                $this->rememberAddress($user->id, $request);
            }
            Notifier::send($user, new OrderReceived($order));        // sau commit, qua hàng đợi; lỗi gửi mail không làm hỏng đơn
        }

        if ($order->status === OrderStatus::PendingPayment && $order->payment_method === PaymentMethod::Vnpay) {
            try {
                return redirect()->away($payments->checkoutUrl($order, route('payment.vnpay.return'), (string) $request->ip()));
            } catch (BusinessException $e) {
                return redirect()->route('orders.show', $order)->with('error', $e->getMessage());
            }
        }

        return redirect()->route('orders.show', $order)->with('success', "Đặt hàng thành công! Mã đơn: {$order->code}.");
    }

    private function rememberAddress(int $userId, PlaceOrderRequest $request): void
    {
        app(AddressService::class)->rememberFromOrder($userId,
            $request->safe()->only(['recipient_name', 'phone', 'province', 'district', 'ward', 'address_line']));
    }
}

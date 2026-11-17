<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Exceptions\BusinessException;
use App\Exceptions\CouponNotApplicableException;
use App\Exceptions\PriceChangedException;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\UserAddress;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PricingService;
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

    public function form(Request $request)
    {
        foreach ($this->cart->reconcile() as $notice) {
            session()->now('warning', $notice);
        }
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng đang trống!');
        }

        $user = $request->user()->load('tier');
        $couponCode = session(self::COUPON_KEY);
        try {
            $quote = $this->pricing->quoteCart($user, $this->cart->items(), $couponCode);
        } catch (CouponNotApplicableException $e) {
            session()->forget(self::COUPON_KEY);                     // mã hết hiệu lực -> bỏ mã, báo khách
            session()->now('error', $e->getMessage().' Mã đã được gỡ khỏi đơn.');
            $quote = $this->pricing->quoteCart($user, $this->cart->items());
        } catch (BusinessException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        if (! session()->has(self::TOKEN_KEY)) {
            session([self::TOKEN_KEY => (string) Str::uuid()]);
        }

        return view('checkout.form', [
            'quote' => $quote,
            'user' => $user,
            'addresses' => $user->addresses()->get(),
            'methods' => PaymentMethod::available(),
            'token' => session(self::TOKEN_KEY),
            'nextTier' => $user->tier->next(),
        ]);
    }

    public function applyCoupon(Request $request)
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

        return back()->with('success', "Đã áp dụng mã {$quote->coupon->code}: giảm ".number_format($quote->couponBenefit(), 0, ',', '.').'đ.');
    }

    public function removeCoupon()
    {
        session()->forget(self::COUPON_KEY);

        return back()->with('success', 'Đã bỏ mã giảm giá.');
    }

    public function store(PlaceOrderRequest $request, OrderService $orders)
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
            try {
                OrderPlaced::dispatch($order);                         // lỗi gửi mail không làm hỏng đơn
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($order->status === OrderStatus::PendingPayment && $order->payment_method === PaymentMethod::Vnpay) {
            return redirect()->route('orders.pay', $order);
        }

        return redirect()->route('orders.show', $order)->with('success', "Đặt hàng thành công! Mã đơn: {$order->code}.");
    }

    private function rememberAddress(int $userId, PlaceOrderRequest $request): void
    {
        $fields = $request->safe()->only(['recipient_name', 'phone', 'province', 'district', 'ward', 'address_line']);
        $exists = UserAddress::where('user_id', $userId)->where($fields)->exists();
        if (! $exists) {
            UserAddress::create($fields + [
                'user_id' => $userId,
                'is_default' => ! UserAddress::where('user_id', $userId)->where('is_default', true)->exists(),
            ]);
        }
    }
}

<?php

namespace Tests\Concerns;

use App\Enums\PaymentMethod;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\PlantUnit;
use App\Models\User;
use App\Services\Orders\PlaceOrderData;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait BuildsOrders
{
    protected function customer(): User
    {
        return User::where('email', 'khach@greenlife.test')->firstOrFail();
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@greenlife.test')->firstOrFail();
    }

    protected function withTier(User $user, string $code): User
    {
        $user->forceFill(['membership_tier_id' => MembershipTier::where('code', $code)->value('id')])->save();

        return $user->refresh();
    }

    protected function shipping(): array
    {
        // Địa giới từ 01/07/2025: Tỉnh -> Phường (district = tên tỉnh, server tự ghi)
        return ['recipient_name' => 'Nguyễn Văn Khách', 'phone' => '0900000002', 'province' => 'Thành phố Hồ Chí Minh',
            'district' => 'Thành phố Hồ Chí Minh', 'ward' => 'Phường Sài Gòn', 'address_line' => '12 Nguyễn Huệ'];
    }

    /** @param array<int,int> $cart */
    protected function place(?User $user = null, array $cart = [1 => 1], PaymentMethod $method = PaymentMethod::Cod,
        ?string $coupon = null, ?string $token = null, ?int $expectedTotal = null): Order
    {
        return app(OrderService::class)->place($user ?? $this->customer(), new PlaceOrderData(
            cart: $cart, shipping: $this->shipping(), paymentMethod: $method, checkoutToken: $token ?? (string) Str::uuid(),
            couponCode: $coupon, expectedTotal: $expectedTotal, note: null, clientIp: '127.0.0.1',
        ))->refresh();
    }

    /** Gửi IPN đã ký đúng (trừ khi $tamper) cho 1 charge. */
    protected function ipn(Payment $payment, bool $success = true, ?int $amount = null, bool $tamper = false): string
    {
        $params = [
            'vnp_TmnCode' => 'FAKE', 'vnp_TxnRef' => $payment->txn_ref, 'vnp_Amount' => (string) (($amount ?? $payment->amount) * 100),
            'vnp_ResponseCode' => $success ? '00' : '24', 'vnp_TransactionStatus' => $success ? '00' : '02',
            'vnp_TransactionNo' => (string) random_int(10000000, 99999999), 'vnp_BankCode' => 'NCB',
        ];
        parse_str(app(PaymentGateway::class)->sign($params), $query);
        if ($tamper) {
            $query['vnp_Amount'] = '100';
        }

        return app(PaymentService::class)->handleCallback($query, '127.0.0.1');
    }

    protected function stock(int $plantId): int
    {
        return Plant::withTrashed()->findOrFail($plantId)->stock;
    }

    /**
     * Mã các chậu "trong kho" đủ cho mọi dòng của đơn (như nhân viên quét khi đóng gói), chậu nhập trước lấy trước.
     *
     * @return list<string>
     */
    protected function packCodes(Order $order): array
    {
        $codes = [];
        foreach ($order->items()->get() as $item) {
            array_push($codes, ...PlantUnit::where('plant_id', $item->plant_id)->where('status', 'in_stock')
                ->whereNotIn('code', $codes)->orderBy('id')->limit($item->quantity)->pluck('code')->all());
        }

        return $codes;
    }

    /** Bất biến kho: plants.stock = SUM(stock_movements), và số chậu trên kệ khớp tồn, cho mọi cây. */
    protected function assertLedgerConsistent(): void
    {
        $drift = DB::table('plants as p')
            ->whereRaw('p.stock <> (SELECT COALESCE(SUM(quantity),0) FROM stock_movements s WHERE s.plant_id = p.id)')
            ->pluck('id')->all();
        $this->assertSame([], $drift, 'plants.stock lệch sổ kho');
        // Từng chậu: chậu trên kệ = tồn + số đã đặt chưa giao (shop:reconcile #10)
        $units = DB::table('plants as p')->whereRaw("(SELECT COUNT(*) FROM plant_units u WHERE u.plant_id = p.id AND u.status = 'in_stock')
            <> p.stock + (SELECT COALESCE(SUM(i.quantity),0) FROM order_items i JOIN orders o ON o.id = i.order_id
            WHERE i.plant_id = p.id AND o.status IN ('pending_payment','pending','confirmed'))")->pluck('id')->all();
        $this->assertSame([], $units, 'số chậu trong kho lệch tồn');
    }
}

<?php

namespace Tests\Concerns;

use App\Enums\PaymentMethod;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plant;
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
        return ['recipient_name' => 'Nguyễn Văn Khách', 'phone' => '0900000002', 'province' => 'TP. Hồ Chí Minh',
            'district' => 'Quận 1', 'ward' => 'Phường Bến Nghé', 'address_line' => '12 Nguyễn Huệ'];
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

    /** Bất biến kho: plants.stock = SUM(stock_movements) cho mọi cây. */
    protected function assertLedgerConsistent(): void
    {
        $drift = DB::table('plants as p')
            ->whereRaw('p.stock <> (SELECT COALESCE(SUM(quantity),0) FROM stock_movements s WHERE s.plant_id = p.id)')
            ->pluck('id')->all();
        $this->assertSame([], $drift, 'plants.stock lệch sổ kho');
    }
}

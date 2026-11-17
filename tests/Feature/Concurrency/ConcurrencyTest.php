<?php

namespace Tests\Feature\Concurrency;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * Chạy NHIỀU TIẾN TRÌNH PHP THẬT cùng lúc trên MySQL (không dùng transaction của test,
 * vì tiến trình khác không thấy dữ liệu chưa commit). Mỗi test dựng lại DB từ đầu.
 */
#[Group('concurrency')]
class ConcurrencyTest extends TestCase
{
    use BuildsOrders;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    /**
     * @param  list<list<string>>  $jobs  mỗi job: [command, ...args]
     * @return list<array{ok:bool, business?:bool, error?:string, result?:mixed}>
     */
    private function race(array $jobs): array
    {
        $startAt = (string) (microtime(true) + 2.0);
        $env = ['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => config('database.connections.mysql.database'), 'APP_ENV' => 'testing'];
        $procs = [];
        foreach ($jobs as $job) {
            $p = new Process(array_merge([PHP_BINARY, base_path('tests/Concurrency/worker.php'), $startAt], $job), base_path(), $env, null, 60);
            $p->start();
            $procs[] = $p;
        }
        $out = [];
        foreach ($procs as $p) {
            $p->wait();
            $decoded = json_decode(trim($p->getOutput()), true);
            $this->assertIsArray($decoded, 'Worker lỗi: '.$p->getOutput().$p->getErrorOutput());
            $this->assertTrue($decoded['ok'] || $decoded['business'], 'Lỗi hệ thống (không phải nghiệp vụ): '.($decoded['error'] ?? ''));
            $out[] = $decoded;
        }

        return $out;
    }

    public function test_many_customers_racing_for_the_last_plant(): void
    {
        $users = User::factory()->count(6)->create();                       // cây #16 chỉ còn 1
        $results = $this->race($users->map(fn ($u) => ['place', (string) $u->id, json_encode([16 => 1]), (string) Str::uuid()])->all());

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(0, $this->stock(16));
        $this->assertSame(1, Order::count());
        $this->assertLedgerConsistent();
    }

    public function test_double_submit_from_many_tabs_creates_exactly_one_order(): void
    {
        $user = $this->customer();
        $results = $this->race(array_fill(0, 5, ['place', (string) $user->id, json_encode([1 => 2]), 'same-token']));

        $this->assertSame(1, collect($results)->pluck('result')->unique()->count());
        $this->assertSame(1, Order::count());
        $this->assertSame(28, $this->stock(1));
        $this->assertLedgerConsistent();
    }

    public function test_ipn_and_expiry_job_racing_never_leave_inconsistent_money(): void
    {
        for ($round = 0; $round < 4; $round++) {
            $order = $this->place(cart: [1 => 1], method: PaymentMethod::Vnpay);
            Order::whereKey($order->id)->update(['payment_expires_at' => now()->subHour()]);
            $this->race([['ipn', $order->payments()->sole()->txn_ref], ['expire']]);

            $order->refresh();
            $this->assertContains([$order->status, $order->payment_status], [
                [OrderStatus::Pending, PaymentStatus::Paid],                // IPN thắng: giữ đơn
                [OrderStatus::Cancelled, PaymentStatus::RefundPending],     // hủy thắng: tiền về sau -> hoàn
            ]);
        }
        $this->assertLedgerConsistent();
    }

    public function test_cancellations_and_new_orders_on_same_plants_do_not_deadlock(): void
    {
        $users = User::factory()->count(4)->create();
        $existing = collect(range(1, 4))->map(fn () => $this->place(cart: [3 => 1, 1 => 1]))->all();

        $jobs = [];
        foreach ($users as $i => $u) {
            $jobs[] = ['place', (string) $u->id, json_encode([1 => 1, 3 => 1]), (string) Str::uuid()];
            $jobs[] = ['cancel', (string) $existing[$i]->id];
        }
        $results = $this->race($jobs);

        $this->assertSame(8, collect($results)->where('ok', true)->count());
        $this->assertSame(4, Order::status(OrderStatus::Cancelled)->count());
        $this->assertSame(26, $this->stock(1));                            // 30 - 4 đơn mới
        $this->assertLedgerConsistent();
    }

    /** B7: nhiều người cùng tạo yêu cầu nhập hàng -> mã chứng từ không trùng, không lỗi hệ thống. */
    public function test_document_codes_are_unique_under_concurrency(): void
    {
        $results = $this->race(array_fill(0, 5, ['draft-pr']));

        $codes = collect($results)->pluck('result');
        $this->assertSame(5, $codes->filter()->unique()->count(), 'Mã yêu cầu nhập bị trùng: '.$codes->implode(', '));
    }

    /** Nhập kho chạy song song với đặt hàng trên cùng cây: không deadlock, kho khớp sổ. */
    public function test_goods_receipt_and_orders_on_same_plant(): void
    {
        $purchases = app(PurchaseService::class);
        $staff = User::factory()->staff()->create();
        $pr = $purchases->createRequest($staff, [1 => ['quantity' => 20, 'estimated_unit_cost' => 100000]]);
        $purchases->transition($pr, PurchaseRequestStatus::Submitted, $staff);
        $purchases->approve($pr, $this->admin());
        $receipt = $purchases->createReceipt($staff, Supplier::firstOrFail(), $pr->refresh(), [1 => ['quantity_received' => 20, 'unit_cost' => 120000]]);
        $users = User::factory()->count(4)->create();

        $jobs = [['complete-receipt', (string) $receipt->id]];
        foreach ($users as $u) {
            $jobs[] = ['place', (string) $u->id, json_encode([1 => 1, 3 => 1]), (string) Str::uuid()];
        }
        $results = $this->race($jobs);

        $this->assertSame(5, collect($results)->where('ok', true)->count());
        $this->assertSame(30 + 20 - 4, $this->stock(1));
        $this->assertLedgerConsistent();
    }
}

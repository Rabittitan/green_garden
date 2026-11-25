<?php

namespace Tests\Feature\Concurrency;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\Coupon;
use App\Models\CouponGiftRule;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
     * Test này COMMIT dữ liệu thật (tiến trình khác mới thấy được). Báo cho RefreshDatabase của lớp test
     * kế tiếp dựng lại DB sạch, nếu không lớp sau sẽ chạy trên dữ liệu thừa (vd tồn kho 29 thay vì 30).
     */
    public static function tearDownAfterClass(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDownAfterClass();
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
        $results = $this->race(array_fill(0, 5, ['draft-pr', (string) User::factory()->staff()->create()->id]));   // nhân viên lập

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

    /**
     * Nhóm B: admin thêm cây vào phạm vi mã (sync coupon_plants -> khóa ngoại cần dòng plants) đúng lúc nhiều khách
     * đặt hàng cây đó bằng chính mã đó (checkout khóa plants -> coupons). Code cũ khóa coupons trước -> deadlock 1213.
     * Yêu cầu: không lỗi hệ thống; khi mã đã có người dùng thì luật (giá trị) và phạm vi KHÔNG bị đổi nửa vời.
     */
    public function test_admin_editing_coupon_scope_while_customers_check_out_with_it(): void
    {
        for ($round = 0; $round < 3; $round++) {
            $coupon = Coupon::create(['code' => 'RACE'.$round, 'name' => 'Race', 'type' => 'percent', 'value' => 10,
                'scope' => 'plants', 'usage_limit_per_user' => 5]);
            $coupon->plants()->sync([1, 3, 5]);
            $users = User::factory()->count(3)->create();
            $jobs = $users->map(fn ($u) => ['place', (string) $u->id, json_encode([3 => 1, 5 => 1]), (string) Str::uuid(), 'cod', 'RACE'.$round])->all();
            $jobs[] = ['coupon-update', (string) $coupon->id, json_encode([1, 3, 5, 7, 12]), '20'];
            $jobs[] = ['coupon-update', (string) $coupon->id, json_encode([1, 3, 5, 9]), '30'];
            $results = $this->race($jobs);                                  // race() đã chặn mọi lỗi không phải nghiệp vụ (vd deadlock)

            $coupon->refresh();
            $placed = collect($results)->take(3)->where('ok', true)->count();
            $this->assertSame($placed, $coupon->used_count);
            $scope = $coupon->plants()->pluck('plants.id')->sort()->values()->all();
            if ($placed > 0 && $coupon->value === 10) {                      // khách dùng trước admin: luật + phạm vi giữ nguyên
                $this->assertSame([1, 3, 5], $scope);
            } else {                                                         // admin sửa trước: luật và phạm vi đổi CÙNG NHAU
                $this->assertContains([$coupon->value, $scope], [[20, [1, 3, 5, 7, 12]], [30, [1, 3, 5, 9]]]);
            }
        }
        $this->assertLedgerConsistent();
        $this->artisan('shop:reconcile')->assertSuccessful();
    }

    /** Đợt 3: nhiều tiến trình phát quà cùng lúc (cron chồng nhau) -> mỗi đơn đủ điều kiện được tặng ĐÚNG 1 mã. */
    public function test_gift_issuing_runs_in_parallel_without_duplicates(): void
    {
        $rule = CouponGiftRule::create(['name' => 'Quà', 'min_order_total' => 1, 'type' => 'fixed', 'value' => 20000, 'valid_days' => 30]);
        $rule->forceFill(['created_at' => now()->subDays(30)])->save();
        $orders = app(OrderService::class);
        foreach (range(1, 4) as $i) {
            $o = $this->place(User::factory()->create(), [1 => 1]);
            $orders->confirm($o, $this->admin());
            $orders->ship($o, 'GHN', 'G'.$i, $this->admin());
            $orders->complete($o, $this->admin());
        }
        DB::table('orders')->update(['completed_at' => now()->subDays(10)]);

        $results = $this->race(array_fill(0, 4, ['issue-gifts']));
        $this->assertSame(4, collect($results)->sum('result'));
        $this->assertSame(4, Coupon::where('gift_rule_id', $rule->id)->count());
        $this->assertSame(4, Coupon::where('gift_rule_id', $rule->id)->distinct()->count('source_order_id'));
    }

    /** Đợt 4: 5 khách tranh 2 suất flash sale cuối -> đúng 2 cây bán giá sale, sold_count khớp, không bán lố suất. */
    public function test_customers_racing_for_last_flash_sale_slots(): void
    {
        $sale = new FlashSale(['name' => 'Race', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $sale->forceFill(['created_by' => User::factory()->staff()->create()->id, 'status' => 'approved', 'approved_by' => $this->admin()->id, 'approved_at' => now()])->save();
        $item = $sale->items()->create(['plant_id' => 3, 'sale_price' => 99000, 'quantity_limit' => 2]);
        $users = User::factory()->count(5)->create();
        $results = $this->race($users->map(fn ($u) => ['place', (string) $u->id, json_encode([3 => 1]), (string) Str::uuid()])->all());

        $this->assertSame(5, collect($results)->where('ok', true)->count());                  // ai cũng đặt được, quá suất thì giá thường
        $this->assertSame(2, $item->refresh()->sold_count);
        $this->assertSame(2, OrderItem::where('flash_sale_item_id', $item->id)->where('unit_price', 99000)->count());
        $this->assertSame(3, OrderItem::where('plant_id', 3)->whereNull('flash_sale_item_id')->where('unit_price', Plant::find(3)->price)->count());
        $this->assertLedgerConsistent();
        $this->artisan('shop:reconcile')->assertSuccessful();
    }

    /** Bấm "Gửi đánh giá" nhiều lần cùng lúc: đúng 1 đánh giá, không lỗi UNIQUE (lỗi cũ trong ReviewService). */
    public function test_double_review_submit_creates_one_review(): void
    {
        $orders = app(OrderService::class);
        $o = $this->place();
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'C1', $this->admin());
        $orders->complete($o, $this->admin());

        $results = $this->race(array_fill(0, 4, ['review', (string) $this->customer()->id, '1']));
        $this->assertSame(1, collect($results)->pluck('result')->unique()->count());
        $this->assertSame(1, PlantReview::count());
        // Đánh giá hiện ngay: dù gửi chồng 4 lần, điểm sao chỉ tính 1 lần
        $this->assertSame([5, 1], [Plant::find(1)->rating_sum, Plant::find(1)->rating_count]);
        $this->artisan('shop:reconcile')->assertSuccessful();
    }

    /** Nhân viên hủy đơn đúng lúc VNPay báo đã trả tiền: luôn kết thúc ở "đã hủy + chờ hoàn tiền", kho trả về đủ. */
    public function test_staff_cancel_racing_ipn_always_ends_cancelled_with_refund(): void
    {
        for ($round = 0; $round < 3; $round++) {
            $order = $this->place(cart: [1 => 1], method: PaymentMethod::Vnpay);
            $this->race([['ipn', $order->payments()->sole()->txn_ref], ['cancel', (string) $order->id]]);
            $order->refresh();
            $this->assertSame([OrderStatus::Cancelled, PaymentStatus::RefundPending], [$order->status, $order->payment_status]);
        }
        $this->assertSame(30, $this->stock(1));
        $this->assertLedgerConsistent();
    }

    /** "Giao" và "Hủy" cùng lúc trên 1 đơn: đúng 1 bên thắng, kho đúng theo bên thắng. */
    public function test_ship_racing_cancel_has_exactly_one_winner(): void
    {
        $order = $this->place(cart: [1 => 2]);
        app(OrderService::class)->confirm($order, $this->admin());
        $results = $this->race([['ship', (string) $order->id], ['cancel', (string) $order->id]]);

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $order->refresh();
        $this->assertContains($order->status, [OrderStatus::Shipping, OrderStatus::Cancelled]);
        $this->assertSame($order->status === OrderStatus::Cancelled ? 30 : 28, $this->stock(1));
        $this->assertLedgerConsistent();
    }

    /** 2 admin cùng xác nhận đã hoàn 1 khoản: chỉ ghi nhận 1 lần, không hoàn trùng. */
    public function test_double_refund_confirmation_is_recorded_once(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->ipn($order->payments()->sole());
        app(OrderService::class)->cancel($order->refresh(), ActorType::Staff, $this->admin(), 'x');
        $refund = $order->refunds()->sole();

        $results = $this->race(array_fill(0, 3, ['confirm-refund', (string) $refund->id]));
        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $order->refresh();
        $this->assertSame([280000, PaymentStatus::Refunded], [$order->refunded_amount, $order->payment_status]);
    }

    /** 3 admin cùng bấm "Xác nhận": 1 lần thành công, lịch sử chỉ có 1 dòng. */
    public function test_triple_confirm_writes_one_history_row(): void
    {
        $order = $this->place();
        $results = $this->race(array_fill(0, 3, ['confirm', (string) $order->id]));
        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(1, $order->histories()->where('to_status', 'confirmed')->count());
    }
}

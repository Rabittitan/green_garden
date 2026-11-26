<?php

namespace Tests\Feature\Services;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlantUnitStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Plant;
use App\Models\PlantUnit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Support\UnitCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Mã riêng cho từng chậu (góp ý 26/11): phát mã khi nhập, quét khi giao, chậu về kho khi hoàn, tra cứu, in tem. */
class PlantUnitTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    /** @return list<string> */
    private function shelf(int $plantId, int $n, int $skip = 0): array
    {
        return PlantUnit::where('plant_id', $plantId)->where('status', 'in_stock')->orderBy('id')->skip($skip)->take($n)->pluck('code')->all();
    }

    private function confirmed(array $cart = [1 => 2], ?User $user = null): Order
    {
        $o = $this->place($user, $cart);

        return $this->orders()->confirm($o, $this->admin());
    }

    public function test_every_plant_on_the_shelf_has_its_own_code(): void
    {
        foreach (Plant::all() as $p) {
            $this->assertSame($p->stock, $p->units()->where('status', 'in_stock')->count(), $p->name);
        }
        $codes = PlantUnit::pluck('code');
        $this->assertSame($codes->count(), $codes->unique()->count());
        $this->assertTrue($codes->every(fn ($c) => UnitCode::normalize($c) === $c));
        $this->assertSame('created', PlantUnit::first()->events()->sole()->type);
        $this->assertSame('7KQ2M9XA', UnitCode::normalize(' 7kq2-m9xa '));
        $this->assertSame('1000000A', UnitCode::normalize('iOoo-OOOa'));                  // gõ nhầm I/O
        $this->assertNull(UnitCode::normalize('7KQ2M9X'));
    }

    public function test_goods_receipt_issues_one_code_per_pot_and_prints_labels(): void
    {
        $staff = User::factory()->staff()->create();
        $purchases = app(PurchaseService::class);
        $receipt = $purchases->createReceipt($staff, Supplier::where('code', 'NCC-001')->firstOrFail(), null,
            [3 => ['quantity_received' => 4, 'unit_cost' => 50000]]);
        $purchases->completeReceipt($receipt, $staff->id);

        $item = $receipt->items()->sole();
        $new = PlantUnit::where('goods_receipt_item_id', $item->id)->get();
        $this->assertCount(4, $new);
        $this->assertTrue($new->every(fn ($u) => $u->status === PlantUnitStatus::InStock && $u->plant_id === 3));
        $this->assertLedgerConsistent();

        $this->actingAs($staff);
        $this->get(route('admin.goods-receipts.show', $receipt))->assertOk()->assertSee(route('admin.units.labels', ['receipt' => $receipt->id]), false);
        $labels = $this->get(route('admin.units.labels', ['receipt' => $receipt->id]))->assertOk()->getContent();
        $this->assertSame(4, substr_count($labels, 'class="label"'));
        foreach ($new as $u) {
            $this->assertStringContainsString('data-qr="'.$u->code.'"', $labels);
            $this->assertStringContainsString($u->displayCode(), $labels);
        }
        $this->assertStringContainsString('@page { size: 50mm 30mm; margin: 0; }', $labels);
    }

    public function test_shipping_assigns_exactly_the_scanned_pots(): void
    {
        $o = $this->confirmed([1 => 2, 3 => 1]);
        $codes = [...$this->shelf(1, 2), ...$this->shelf(3, 1)];
        $this->orders()->ship($o, 'GHN', 'T1', $this->admin(), array_map(fn ($c) => strtolower(UnitCode::format($c)), $codes));   // máy quét / gõ tay

        $sold = PlantUnit::whereIn('code', $codes)->get();
        $this->assertTrue($sold->every(fn ($u) => $u->status === PlantUnitStatus::Sold));
        $items = $o->items()->get()->keyBy('plant_id');
        $this->assertSame([$items[1]->id, $items[1]->id, $items[3]->id], $sold->sortBy('plant_id')->pluck('order_item_id')->values()->all());
        $this->assertSame(2, DB::table('plant_unit_events')->where('type', 'shipped')->where('order_item_id', $items[1]->id)->count());
        $this->assertLedgerConsistent();
    }

    /** Quét thiếu / thừa / sai cây / chậu đã giao / mã lạ: không giao, không đổi chậu nào. */
    public function test_wrong_scans_are_rejected_without_side_effects(): void
    {
        $o = $this->confirmed([1 => 2]);
        $other = $this->confirmed([1 => 1], User::factory()->create());
        $this->orders()->ship($other, 'GHN', 'X', $this->admin(), $taken = $this->shelf(1, 1));
        $good = $this->shelf(1, 3);

        $cases = [
            'thiếu' => [[$good[0]], 'cần 2 chậu, mới quét 1'],
            'thừa' => [[$good[0], $good[1], $good[2]], 'Quét thừa'],
            'sai cây' => [[$good[0], $good[1], $this->shelf(3, 1)[0]], 'Quét thừa / sai cây'],
            'đã giao' => [[$good[0], $taken[0]], 'Đã giao'],
            'quét 2 lần' => [[$good[0], $good[0]], 'bị quét 2 lần'],
            'mã lạ' => [[$good[0], 'ZZZZZZZZ'], 'Không có chậu nào mang mã ZZZZ-ZZZZ'],
            'sai định dạng' => [[$good[0], 'abc'], 'không phải mã chậu hợp lệ'],
        ];
        foreach ($cases as $label => [$codes, $message]) {
            try {
                $this->orders()->ship($o, 'GHN', 'T', $this->admin(), $codes);
                $this->fail("Phải chặn: {$label}");
            } catch (BusinessException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $label);
            }
            $this->assertSame(OrderStatus::Confirmed, $o->refresh()->status, $label);
            $this->assertSame(0, PlantUnit::whereIn('code', $good)->where('status', '<>', 'in_stock')->count(), $label);
        }
        $this->assertLedgerConsistent();
    }

    public function test_return_brings_good_pots_back_and_writes_off_the_scanned_broken_ones(): void
    {
        $o = $this->confirmed([1 => 3]);
        $codes = $this->shelf(1, 3);
        $this->orders()->ship($o, 'GHN', 'T', $this->admin(), $codes);
        $this->orders()->markReturning($o, ActorType::Staff, $this->admin(), 'Khách không nhận');
        $item = $o->items()->sole();

        try {                                                                  // khai 1 hỏng nhưng quét chậu không thuộc đơn
            $this->orders()->receiveReturn($o, [$item->id => ['good' => 2, 'damaged' => 1]], $this->admin(), damagedCodes: [$this->shelf(1, 1)[0]]);
            $this->fail('Phải chặn chậu không thuộc đơn');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('khai 1 chậu hỏng nhưng quét 0', $e->getMessage());
        }
        $this->assertSame(OrderStatus::Returning, $o->refresh()->status);

        $this->orders()->receiveReturn($o, [$item->id => ['good' => 2, 'damaged' => 1]], $this->admin(), damagedCodes: [UnitCode::format($codes[1])]);
        $units = PlantUnit::whereIn('code', $codes)->get()->keyBy('code');
        $this->assertSame([PlantUnitStatus::InStock, PlantUnitStatus::Damaged, PlantUnitStatus::InStock],
            [$units[$codes[0]]->status, $units[$codes[1]]->status, $units[$codes[2]]->status]);
        $this->assertSame(['created', 'shipped', 'returned', 'damaged'], $units[$codes[1]]->events()->pluck('type')->all());
        $this->assertLedgerConsistent();
    }

    /** Đơn giao TRƯỚC khi có mã từng chậu (không có chậu nào gắn đơn): hoàn về thì phát mã mới cho chậu về kho. */
    public function test_return_of_an_order_shipped_before_unit_codes_issues_new_codes(): void
    {
        $o = $this->confirmed([5 => 2]);
        $this->orders()->ship($o, 'GHN', 'T', $this->admin());
        PlantUnit::where('order_item_id', $o->items()->sole()->id)->update(['status' => 'lost', 'order_item_id' => null]);   // giả lập đơn cũ
        $this->orders()->markReturning($o, ActorType::Staff, $this->admin(), 'Hoàn');
        $before = PlantUnit::count();

        $this->orders()->receiveReturn($o, [$o->items()->sole()->id => ['good' => 2, 'damaged' => 0]], $this->admin(), damagedCodes: []);
        $this->assertSame($before + 2, PlantUnit::count());
        $this->assertSame(2, DB::table('plant_unit_events')->where('note', 'like', '%in tem dán lại%')->count());
        $this->assertLedgerConsistent();
    }

    public function test_cancel_before_shipping_touches_no_pot(): void
    {
        $o = $this->confirmed([1 => 2]);
        $this->orders()->cancel($o, ActorType::Staff, $this->admin(), 'Khách đổi ý');
        $this->assertSame(0, PlantUnit::where('status', '<>', 'in_stock')->count());
        $this->assertLedgerConsistent();
    }

    public function test_lookup_check_and_labels_pages(): void
    {
        $o = $this->confirmed([1 => 1]);
        $code = $this->shelf(1, 1)[0];
        $this->orders()->ship($o, 'GHN', 'T', $this->admin(), [$code]);

        $staff = User::factory()->staff()->create(['permissions' => ['orders']]);        // người đóng gói: chỉ có quyền đơn hàng
        $this->actingAs($staff)->getJson(route('admin.units.check', ['code' => strtolower($code)]))->assertOk()
            ->assertJson(['code' => $code, 'plant_id' => 1, 'status' => 'sold', 'status_label' => 'Đã giao']);
        $this->getJson(route('admin.units.check', ['code' => 'ZZZZZZZZ']))->assertNotFound();
        $this->getJson(route('admin.units.check', ['code' => 'x']))->assertStatus(422);
        $this->get(route('admin.units.index', ['ma' => $code]))->assertForbidden();        // tra cứu: nhóm Kho

        $this->actingAs(User::factory()->staff()->create(['permissions' => ['inventory']]));
        $this->get(route('admin.units.index', ['ma' => UnitCode::format($code)]))->assertOk()
            ->assertSee('data-testid="unit-detail"', false)->assertSee($o->code)->assertSee('Giao cho đơn')->assertSee('Nhập kho / phát mã');
        $this->get(route('admin.units.index', ['plant' => 1]))->assertOk()->assertSee('Trong kho ('.Plant::find(1)->stock.')');
        $labels = $this->get(route('admin.units.labels', ['plant' => 1]))->assertOk()->getContent();
        $this->assertSame(Plant::find(1)->stock, substr_count($labels, 'class="label"'));
        $this->assertStringNotContainsString('data-qr="'.$code.'"', $labels);           // chậu đã giao không in lại theo cây
        $this->get(route('admin.units.labels'))->assertOk()->assertSee('Không có chậu nào để in.');
        $this->get(route('admin.inventory.show', 1))->assertOk()->assertSee('data-testid="units-card"', false);

        $this->actingAs($this->customer())->getJson(route('admin.units.check', ['code' => $code]))->assertForbidden();
    }

    public function test_order_page_shows_packing_scanner_and_shipped_codes(): void
    {
        $o = $this->confirmed([1 => 2]);
        $this->actingAs($this->admin());
        $this->get(route('admin.orders.show', $o))->assertOk()->assertSee('id="dong-goi"', false)->assertSee('data-pack-mode="ship"', false)
            ->assertSee(route('admin.units.check'), false);
        $codes = $this->shelf(1, 2);
        $this->post(route('admin.orders.ship', $o), ['shipping_carrier' => 'GHN', 'tracking_code' => 'A1', 'unit_codes' => [$codes[0]]])
            ->assertSessionHas('error', 'Dòng "'.$o->items()->sole()->plant_name.'" cần 2 chậu, mới quét 1.');
        $this->post(route('admin.orders.ship', $o), ['shipping_carrier' => 'GHN', 'tracking_code' => 'A1', 'unit_codes' => $codes])->assertSessionHas('success');
        $this->get(route('admin.orders.show', $o))->assertSee('data-testid="item-units"', false)->assertSee(UnitCode::format($codes[0]))
            ->assertDontSee('id="dong-goi"', false);
    }

    /** Migration bật tính năng: phát mã cho hàng trên kệ = tồn + đã đặt chưa giao. */
    public function test_migration_backfill_covers_stock_and_reserved_pots(): void
    {
        $this->place(cart: [2 => 3], method: PaymentMethod::Vnpay);                         // đang giữ hàng chờ thanh toán
        $this->confirmed([4 => 2]);
        DB::table('plant_unit_events')->delete();
        DB::table('plant_units')->delete();

        $migration = require database_path('migrations/2026_11_26_000200_create_plant_units.php');
        (fn () => $this->issueForCurrentStock())->call($migration);
        $this->assertSame(Plant::find(2)->stock + 3, PlantUnit::where('plant_id', 2)->count());
        $this->assertLedgerConsistent();
    }
}

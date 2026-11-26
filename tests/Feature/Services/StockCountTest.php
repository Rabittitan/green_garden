<?php

namespace Tests\Feature\Services;

use App\Enums\PlantUnitStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Plant;
use App\Models\PlantUnit;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\StockCountService;
use App\Support\Format;
use App\Support\UnitCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * Phiếu kiểm kê bằng QUÉT MÃ TỪNG CHẬU (góp ý 26/11, thay cho gõ số đếm): chỉ quản trị viên;
 * chốt phiếu: chậu nằm yên mà không quét -> thất lạc, chậu thất lạc quét thấy -> về kho.
 */
class StockCountTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function svc(): StockCountService
    {
        return app(StockCountService::class);
    }

    /** Quét mọi chậu trên kệ (trong kho hoặc thất lạc) của các cây trong phiếu, trừ $skip. @param list<string> $skip */
    private function scanShelf(StockCount $count, array $skip = []): void
    {
        $plants = $count->items()->pluck('plant_id');
        foreach (PlantUnit::whereIn('plant_id', $plants)->whereIn('status', ['in_stock', 'lost'])->whereNotIn('code', $skip)->pluck('code') as $code) {
            $this->svc()->scan($count, $this->admin(), $code);
        }
    }

    public function test_unscanned_steady_pots_become_lost_and_scanned_lost_pots_come_back(): void
    {
        $cat = Plant::find(1)->category_id;
        $count = $this->svc()->create($this->admin(), $cat, 'Cuối tháng');
        $this->assertSame(30, $count->items()->where('plant_id', 1)->value('system_qty'));
        $lost = PlantUnit::where('plant_id', 1)->orderBy('id')->take(2)->pluck('code')->all();

        // Trong lúc đếm: khách đặt 3 cây #1 (chậu vẫn trên kệ, được quét); nhập thêm 4 chậu #1 mà chưa kịp quét
        $this->place(cart: [1 => 3]);
        $staff = User::factory()->staff()->create();
        $receipt = app(PurchaseService::class)->createReceipt($staff, Supplier::where('code', 'NCC-001')->firstOrFail(), null, [1 => ['quantity_received' => 4, 'unit_cost' => 150000]]);
        $newCodes = PlantUnit::where('plant_id', 1)->pluck('code')->all();
        app(PurchaseService::class)->completeReceipt($receipt, $staff->id);
        $fresh = PlantUnit::where('plant_id', 1)->whereNotIn('code', $newCodes)->pluck('code')->all();
        $this->scanShelf($count, [...$lost, ...$fresh]);

        $preview = $this->svc()->preview($count)[$count->items()->where('plant_id', 1)->value('id')];
        $this->assertSame([30, 28, $lost, []], [$preview['expected'], $preview['scanned'], $preview['missing'], $preview['found']]);

        $this->svc()->complete($count, $this->admin());
        $this->assertSame(30 - 3 + 4 - 2, $this->stock(1));                                // bán 3, nhập 4 (không tính oan), mất 2
        $this->assertSame(2, PlantUnit::whereIn('code', $lost)->where('status', PlantUnitStatus::Lost->value)->count());
        $this->assertSame(4, PlantUnit::whereIn('code', $fresh)->where('status', 'in_stock')->count());
        $item = $count->items()->where('plant_id', 1)->sole();
        $this->assertSame([28, -2], [$item->counted_qty, $item->variance()]);
        $adj = StockMovement::where('type', StockMovementType::Adjustment->value)->sole();
        $this->assertSame([-2, $item->id], [$adj->quantity, $adj->stock_count_item_id]);
        $this->assertLedgerConsistent();

        // Lần kiểm sau: tìm thấy 2 chậu thất lạc -> về kho
        $again = $this->svc()->create($this->admin(), $cat, null);
        $this->scanShelf($again);
        $this->svc()->complete($again, $this->admin());
        $this->assertSame(30 - 3 + 4, $this->stock(1));
        $this->assertSame(['created', 'lost', 'found'], PlantUnit::where('code', $lost[0])->sole()->events()->pluck('type')->all());
        $this->assertLedgerConsistent();
    }

    public function test_scan_rejects_pots_that_cannot_be_on_the_shelf(): void
    {
        $cat = Plant::find(1)->category_id;
        $outside = Plant::where('category_id', '<>', $cat)->value('id');
        $count = $this->svc()->create($this->admin(), $cat, null);
        $o = app(OrderService::class)->confirm($this->place(cart: [1 => 1]), $this->admin());
        $sold = PlantUnit::where('plant_id', 1)->orderBy('id')->value('code');
        app(OrderService::class)->ship($o, 'GHN', 'T', $this->admin(), [$sold]);
        $ok = PlantUnit::where('plant_id', 1)->where('status', 'in_stock')->value('code');
        $this->svc()->scan($count, $this->admin(), strtolower(UnitCode::format($ok)));

        foreach ([
            [PlantUnit::where('plant_id', $outside)->value('code'), 'không thuộc phạm vi phiếu'],
            [$sold, 'không thể nằm trên kệ'],
            [$ok, 'đã quét rồi'],
            ['ZZZZZZZZ', 'Không có chậu nào'],
        ] as [$code, $message]) {
            try {
                $this->svc()->scan($count, $this->admin(), $code);
                $this->fail("Phải chặn {$code}");
            } catch (BusinessException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
        $this->svc()->unscan($count, $this->admin(), $ok);
        $this->svc()->scan($count, $this->admin(), $ok);                                        // bỏ quét nhầm rồi quét lại được
    }

    public function test_only_admins_one_open_sheet_and_cancel_leaves_stock_untouched(): void
    {
        try {
            $this->svc()->create(User::factory()->staff()->create(), null, null);
            $this->fail('Nhân viên không lập được phiếu kiểm kê');
        } catch (BusinessException) {
        }
        $count = $this->svc()->create($this->admin(), null, null);
        try {
            $this->svc()->create($this->admin(), null, null);
            $this->fail('Chỉ 1 phiếu đang mở');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('chưa chốt', $e->getMessage());
        }
        $this->svc()->cancel($count, $this->admin());
        $this->assertSame('cancelled', $count->refresh()->status);
        $this->assertSame(0, StockMovement::where('type', 'adjustment')->count());
        $this->expectException(BusinessException::class);
        $this->svc()->scan($count, $this->admin(), PlantUnit::value('code'));
    }

    public function test_admin_pages_scan_and_show_the_variance_report(): void
    {
        $cat = Plant::find(1)->category_id;
        $this->actingAs($this->admin())->post(route('admin.stock-counts.store'), ['category_id' => $cat])->assertRedirect();
        $count = StockCount::sole();
        $item = $count->items()->where('plant_id', 1)->sole();
        $missing = PlantUnit::where('plant_id', 1)->orderBy('id')->take(5)->pluck('code')->all();
        $this->get(route('admin.stock-counts.show', $count))->assertOk()->assertSee('data-count-scan', false)->assertSee('Chưa thấy (sẽ ghi thất lạc)');

        $first = PlantUnit::where('plant_id', 1)->orderByDesc('id')->value('code');
        $this->postJson(route('admin.stock-counts.scan', $count), ['code' => $first])->assertOk()
            ->assertJson(['ok' => true, 'item' => $item->id, 'code' => $first, 'found' => false]);
        $this->postJson(route('admin.stock-counts.scan', $count), ['code' => $first])->assertStatus(422)->assertJson(['ok' => false]);
        $this->postJson(route('admin.stock-counts.scan', $count), ['code' => $first, 'remove' => 1])->assertOk();
        $this->scanShelf($count, $missing);

        $this->get(route('admin.stock-counts.show', $count))->assertSee(UnitCode::format($missing[0]))->assertSee('5</span> chậu · '.Format::money(5 * $item->unit_cost), false);
        $this->put(route('admin.stock-counts.update', $count), ['notes' => [$item->id => 'Mất 5'], 'action' => 'complete'])->assertSessionHas('success');
        $this->assertSame(25, $this->stock(1));
        $this->get(route('admin.stock-counts.show', $count))->assertSee('Đã chốt')->assertSee('5 cây · '.Format::money(5 * $item->unit_cost))
            ->assertSee('Mất 5')->assertDontSee('Chốt phiếu</button>', false);

        $this->actingAs(User::factory()->staff()->create())->postJson(route('admin.stock-counts.scan', $count), ['code' => $first])->assertForbidden();
    }
}

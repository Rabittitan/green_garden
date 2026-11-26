<?php

namespace Tests\Feature\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockCountService;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Đợt 5: phiếu kiểm kê — nghiệp vụ riêng dạng báo cáo, chỉ quản trị viên; chốt phiếu mới điều chỉnh kho. */
class StockCountTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_full_count_flow_applies_only_the_variance(): void
    {
        $svc = app(StockCountService::class);
        $count = $svc->create($this->admin(), 1, 'Cuối tháng');                               // danh mục 1
        $items = $count->items()->get()->keyBy('plant_id');
        $this->assertSame(30, $items[1]->system_qty);
        $this->assertSame(30, $this->stock(1));                                              // lập phiếu KHÔNG đổi kho

        // Trong lúc đếm có khách mua 3 cây #1
        $this->place(cart: [1 => 3]);
        $svc->saveCounts($count, $this->admin(), [$items[1]->id => ['counted_qty' => 28, 'note' => '2 cây héo']]);
        try {
            $svc->complete($count, $this->admin());
            $this->fail('Còn cây chưa đếm thì không chốt được');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('chưa nhập số đếm', $e->getMessage());
        }
        $all = $count->items()->get()->mapWithKeys(fn ($i) => [$i->id => ['counted_qty' => match ($i->plant_id) {
            1 => 28, 7 => $i->system_qty + 1, default => $i->system_qty,                    // #1 thiếu 2, #7 thừa 1
        }]])->all();
        $svc->saveCounts($count, $this->admin(), $all);
        $svc->complete($count->refresh(), $this->admin());

        $this->assertSame(30 - 3 - 2, $this->stock(1));                                      // áp chênh lệch -2, không ghi đè số bán
        $adj = StockMovement::where('type', StockMovementType::Adjustment->value)->get();
        $this->assertSame(2, $adj->count());                                                 // chỉ dòng có chênh lệch
        $this->assertSame([-2, $items[1]->id], [$adj->firstWhere('plant_id', 1)->quantity, $adj->firstWhere('plant_id', 1)->stock_count_item_id]);
        $this->assertSame(['completed', $this->admin()->id], [$count->refresh()->status, $count->completed_by]);
        $this->assertLedgerConsistent();
        $this->artisan('shop:reconcile')->assertSuccessful();

        $this->expectException(BusinessException::class);                                    // đã chốt: không sửa được
        $svc->saveCounts($count, $this->admin(), [$items[1]->id => ['counted_qty' => 1]]);
    }

    public function test_only_admins_one_open_sheet_and_cancel_leaves_stock_untouched(): void
    {
        $svc = app(StockCountService::class);
        try {
            $svc->create(User::factory()->staff()->create(), null, null);
            $this->fail('Nhân viên không lập được phiếu kiểm kê');
        } catch (BusinessException) {
        }
        $count = $svc->create($this->admin(), null, null);
        try {
            $svc->create($this->admin(), null, null);
            $this->fail('Chỉ 1 phiếu đang mở');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('chưa chốt', $e->getMessage());
        }
        $svc->cancel($count, $this->admin());
        $this->assertSame('cancelled', $count->refresh()->status);
        $this->assertSame(0, StockMovement::where('type', 'adjustment')->count());
    }

    public function test_admin_pages_show_the_variance_report(): void
    {
        $this->actingAs($this->admin())->post(route('admin.stock-counts.store'), ['category_id' => 1])->assertRedirect();
        $count = StockCount::sole();
        $item = $count->items()->where('plant_id', 1)->sole();
        $lines = $count->items()->get()->mapWithKeys(fn ($i) => [$i->id => ['counted_qty' => $i->system_qty]])->all();
        $lines[$item->id] = ['counted_qty' => 25, 'note' => 'Mất 5'];
        $this->put(route('admin.stock-counts.update', $count), ['lines' => $lines, 'action' => 'save'])->assertSessionHas('success');
        $page = $this->get(route('admin.stock-counts.show', $count))->assertOk();
        $page->assertSee('Thiếu so với sổ')->assertSee('5 cây · '.Format::money(5 * $item->unit_cost))->assertSee('-5');

        $this->put(route('admin.stock-counts.update', $count), ['lines' => $lines, 'action' => 'complete'])->assertSessionHas('success');
        $this->assertSame(25, $this->stock(1));
        $this->get(route('admin.stock-counts.show', $count))->assertSee('Đã chốt')->assertDontSee('Chốt phiếu</button>', false);
    }
}

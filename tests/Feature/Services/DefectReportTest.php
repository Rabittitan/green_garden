<?php

namespace Tests\Feature\Services;

use App\Enums\ActorType;
use App\Enums\PlantUnitStatus;
use App\Exceptions\BusinessException;
use App\Models\DefectReport;
use App\Models\PlantUnit;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DefectReportService;
use App\Services\OrderService;
use App\Services\PurchaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Phiếu báo hàng lỗi (góp ý 26/11): nhân viên quét chậu lỗi, quản trị viên khác duyệt mới xuất hủy; báo cáo thiệt hại. */
class DefectReportTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function defects(): DefectReportService
    {
        return app(DefectReportService::class);
    }

    /** @return list<string> */
    private function shelf(int $plantId, int $n): array
    {
        return PlantUnit::where('plant_id', $plantId)->where('status', 'in_stock')->orderBy('id')->take($n)->pluck('code')->all();
    }

    public function test_staff_reports_with_photos_and_another_admin_writes_off_exactly_those_pots(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create(['permissions' => ['inventory']]);
        [$a, $b] = $this->shelf(1, 2);
        $c = $this->shelf(3, 1)[0];
        [$s1, $s3] = [$this->stock(1), $this->stock(3)];
        $this->actingAs($staff)->get(route('admin.defect-reports.create'))->assertOk()->assertSee('data-scanner', false);
        $this->post(route('admin.defect-reports.store'), [
            'lines' => [strtolower($a) => ['type' => 'dead', 'note' => 'Úng rễ'], $b => ['type' => 'broken'], $c => ['type' => 'supplier']],
            'note' => 'Lô nhập 20/11', 'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ])->assertRedirect()->assertSessionHas('success');
        $report = DefectReport::with('items')->sole();
        $this->assertSame(['submitted', 3, 2], [$report->status, $report->items->count(), count($report->photos ?? [])]);
        Storage::disk('public')->assertExists(substr($report->photos[0], strlen('/storage/')));
        $this->assertSame([$s1, $s3], [$this->stock(1), $this->stock(3)]);                  // chưa duyệt: không đổi kho

        $this->defects()->approve($report, $this->admin());
        $this->assertSame([$s1 - 2, $s3 - 1], [$this->stock(1), $this->stock(3)]);
        $this->assertSame(3, PlantUnit::whereIn('code', [$a, $b, $c])->where('status', PlantUnitStatus::Damaged->value)->count());
        $moves = StockMovement::whereNotNull('defect_report_item_id')->get();
        $this->assertSame([3, -3], [$moves->count(), (int) $moves->sum('quantity')]);
        $report->refresh()->load('items');
        $this->assertTrue($report->items->every(fn ($i) => $i->unit_cost > 0));
        $this->assertSame(['approved', $this->admin()->id], [$report->status, $report->decided_by]);
        $this->assertLedgerConsistent();

        $this->actingAs($this->admin())->get(route('admin.defect-reports.show', $report))->assertOk()
            ->assertSee('Đã duyệt, đã xuất hủy')->assertSee('Giá trị thiệt hại')->assertSee('Úng rễ');
        $this->get(route('admin.units.index', ['ma' => $a]))->assertSee('Xuất hủy (lỗi)');
    }

    public function test_four_eyes_reject_and_double_processing(): void
    {
        $admin2 = User::factory()->create(['role' => 'admin']);
        $report = $this->defects()->create($this->admin(), [$this->shelf(1, 1)[0] => ['type' => 'pest']], null);
        try {
            $this->defects()->approve($report, $this->admin());
            $this->fail('Người lập không được tự duyệt');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('không được tự duyệt', $e->getMessage());
        }
        try {                                                                  // chặn cả ở tầng DB
            DB::table('defect_reports')->where('id', $report->id)->update(['status' => 'approved', 'decided_by' => $this->admin()->id, 'decided_at' => now()]);
            $this->fail('CHECK 4 mắt phải chặn');
        } catch (QueryException $e) {
            $this->assertStringContainsString('chk_dr_four_eyes', $e->getMessage());
        }

        $this->defects()->reject($report, $admin2, 'Cây chỉ héo lá, còn bán được');
        $this->assertSame(['rejected', 'Cây chỉ héo lá, còn bán được'], [$report->refresh()->status, $report->reject_reason]);
        $this->assertSame(30, $this->stock(1));
        $this->expectException(BusinessException::class);
        $this->defects()->approve($report, $admin2);
    }

    public function test_only_pots_in_stock_and_not_already_reported_can_be_reported(): void
    {
        $staff = User::factory()->staff()->create();
        [$a, $b] = $this->shelf(1, 2);
        $this->defects()->create($staff, [$a => ['type' => 'dead']], null);
        $o = app(OrderService::class)->confirm($this->place(cart: [3 => 1]), $this->admin());
        $sold = $this->shelf(3, 1)[0];
        app(OrderService::class)->ship($o, 'GHN', 'T', $this->admin(), [$sold]);

        $cases = [
            'đã nằm trong phiếu chờ duyệt' => [[$a => ['type' => 'dead']], 'đang chờ duyệt'],
            'đã giao' => [[$sold => ['type' => 'dead']], 'chỉ báo lỗi chậu đang trong kho'],
            'mã lạ' => [['ZZZZZZZZ' => ['type' => 'dead']], 'Không có chậu nào mang mã'],
            'sai định dạng' => [['abc' => ['type' => 'dead']], 'không phải mã chậu hợp lệ'],
            'thiếu loại lỗi' => [[$b => ['type' => 'xyz']], 'Chọn loại lỗi'],
            'phiếu rỗng' => [[], 'cần ít nhất 1 chậu'],
        ];
        foreach ($cases as $label => [$lines, $message]) {
            try {
                $this->defects()->create($staff, $lines, null);
                $this->fail("Phải chặn: {$label}");
            } catch (BusinessException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $label);
            }
        }
        $this->assertSame(1, DefectReport::count());
    }

    /** Chậu đã giao (hoặc mọi chậu còn lại đều đã có đơn) sau khi lập phiếu: duyệt bị chặn, không đổi gì. */
    public function test_approval_rechecks_pots_after_locking(): void
    {
        $code = $this->shelf(16, 1)[0];                                       // cây #16 chỉ có 1 chậu
        $report = $this->defects()->create(User::factory()->staff()->create(), [$code => ['type' => 'dead']], null);
        $o = app(OrderService::class)->confirm($this->place(cart: [16 => 1]), $this->admin());   // khách đặt đúng chậu cuối
        try {
            $this->defects()->approve($report, $this->admin());
            $this->fail('Chậu cuối đã có đơn: phải chặn');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('đều đã có đơn đặt', $e->getMessage());
        }
        app(OrderService::class)->ship($o, 'GHN', 'T', $this->admin(), [$code]);
        try {
            $this->defects()->approve($report, $this->admin());
            $this->fail('Chậu đã giao: phải chặn');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('đã "Đã giao" sau khi lập phiếu', $e->getMessage());
        }
        $this->assertSame('submitted', $report->refresh()->status);
        $this->assertLedgerConsistent();
    }

    public function test_report_groups_losses_by_type_plant_and_supplier(): void
    {
        $staff = User::factory()->staff()->create();
        $purchases = app(PurchaseService::class);
        $receipt = $purchases->createReceipt($staff, Supplier::where('code', 'NCC-001')->firstOrFail(), null, [5 => ['quantity_received' => 3, 'unit_cost' => 40000]]);
        $purchases->completeReceipt($receipt, $staff->id);
        $fromReceipt = PlantUnit::where('goods_receipt_item_id', $receipt->items()->sole()->id)->orderBy('id')->take(2)->pluck('code')->all();

        $r = $this->defects()->create($staff, [$fromReceipt[0] => ['type' => 'supplier'], $fromReceipt[1] => ['type' => 'supplier'],
            $this->shelf(1, 1)[0] => ['type' => 'dead']], null);
        $this->defects()->approve($r, $this->admin());
        // Hàng hoàn bị hỏng cũng là hàng lỗi
        $orders = app(OrderService::class);
        $o = $orders->confirm($this->place(cart: [3 => 1]), $this->admin());
        $orders->ship($o, 'GHN', 'T', $this->admin());
        $orders->markReturning($o, ActorType::Staff, $this->admin(), 'Khách trả');
        $orders->receiveReturn($o, [$o->items()->sole()->id => ['good' => 0, 'damaged' => 1]], $this->admin());
        $pending = $this->defects()->create($staff, [$this->shelf(1, 1)[0] => ['type' => 'dead']], null);   // chưa duyệt: không tính

        $page = $this->actingAs($this->admin())->get(route('admin.defects.report'))->assertOk()->assertSee('1 phiếu báo lỗi đang chờ duyệt');
        $s = $page->viewData('summary');
        $cost = fn (int $plant) => (int) StockMovement::where('plant_id', $plant)->where('type', 'damage')->value('unit_cost');
        $this->assertSame(4, $s['qty']);
        $this->assertSame(2 * $cost(5) + $cost(1) + $cost(3), $s['loss']);
        $this->assertSame([2, 1, 1], [$s['byType']['supplier']['qty'], $s['byType']['dead']['qty'], $s['byType']['return']['qty']]);
        $bySupplier = collect($s['bySupplier'])->keyBy('name');
        $this->assertSame(2, $bySupplier[Supplier::where('code', 'NCC-001')->value('name')]['qty']);
        $this->assertSame(2, $bySupplier['Không rõ (tồn cũ / tồn đầu kỳ)']['qty']);

        Carbon::setTestNow(now()->addDays(40));                               // ngoài kỳ 30 ngày
        $this->assertSame(0, $this->get(route('admin.defects.report'))->viewData('summary')['qty']);
        unset($pending);
    }

    public function test_permissions(): void
    {
        $report = $this->defects()->create(User::factory()->staff()->create(), [$this->shelf(1, 1)[0] => ['type' => 'dead']], null);
        $this->actingAs(User::factory()->staff()->create(['permissions' => ['orders']]));
        $this->get(route('admin.defect-reports.create'))->assertNoAccess();
        $this->get(route('admin.defects.report'))->assertNoAccess();
        $this->actingAs(User::factory()->staff()->create(['permissions' => ['inventory']]));
        $this->get(route('admin.defect-reports.show', $report))->assertOk()->assertSee('chờ quản trị viên duyệt');
        $this->post(route('admin.defect-reports.reject', $report), ['reason' => 'x'])->assertNoAccess();
        $this->actingAs(User::factory()->staff()->create(['permissions' => ['reports']]))->get(route('admin.defects.report'))->assertOk();
        $this->assertSame('submitted', $report->refresh()->status);
    }
}

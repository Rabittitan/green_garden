<?php

namespace Tests\Feature\Services;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseRequestStatus as PR;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Plant;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** H3: nhập hàng — yêu cầu nhập (4 mắt) -> phiếu nhập -> cộng kho + giá vốn bình quân. */
class PurchasingTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private PurchaseService $purchases;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purchases = app(PurchaseService::class);
        $this->staff = User::factory()->staff()->create();
    }

    private function supplier(): Supplier
    {
        return Supplier::where('code', 'NCC-001')->firstOrFail();
    }

    /** @param array<int,int> $items plant_id => số lượng */
    private function approvedRequest(array $items = [1 => 20, 3 => 10], array $approve = []): PurchaseRequest
    {
        $pr = $this->purchases->createRequest($this->staff, array_map(fn ($q) => ['quantity' => $q, 'estimated_unit_cost' => 100000], $items));
        $this->purchases->transition($pr, PR::Submitted, $this->staff);
        $this->purchases->approve($pr, $this->admin(), $approve);

        return $pr->refresh();
    }

    public function test_full_flow_with_partial_receipts_and_weighted_average_cost(): void
    {
        $pr = $this->approvedRequest([1 => 20, 3 => 10]);
        $this->assertSame(PR::Approved, $pr->status);
        $this->assertMatchesRegularExpression('/^YCN-\d{4}-0001$/', $pr->code);
        $this->purchases->markOrdered($pr, $this->supplier(), $this->staff);

        // Đợt 1: cây #1 về 10 tốt + 2 chết, giá 180k
        $r1 = $this->purchases->createReceipt($this->staff, $this->supplier(), $pr->refresh(),
            [1 => ['quantity_received' => 10, 'quantity_rejected' => 2, 'unit_cost' => 180000]], 'HD-01');
        $this->assertSame(30, $this->stock(1));                              // nháp: chưa đổi kho
        $this->purchases->completeReceipt($r1, $this->staff->id);

        $plant = Plant::find(1);
        $this->assertSame(40, $plant->stock);                                // chỉ cộng cây đạt
        $this->assertSame(157500, $plant->cost_price);                       // (30*150k + 10*180k) / 40
        $this->assertSame(PR::PartiallyReceived, $pr->refresh()->status);
        $this->assertSame(StockMovementType::PurchaseReceipt, StockMovement::latest('id')->first()->type);

        // Đợt 2: phần còn lại
        $r2 = $this->purchases->createReceipt($this->staff, $this->supplier(), $pr,
            [1 => ['quantity_received' => 10, 'unit_cost' => 150000], 3 => ['quantity_received' => 10, 'unit_cost' => 50000]]);
        $this->purchases->completeReceipt($r2, $this->staff->id);
        $this->assertSame(PR::Received, $pr->refresh()->status);
        $this->assertSame(GoodsReceiptStatus::Completed, $r2->refresh()->status);
        $this->assertSame(10 * 150000 + 10 * 50000, $r2->total_cost);
        $this->assertLedgerConsistent();
    }

    public function test_admin_only_approves_and_staff_only_requests(): void
    {
        try {
            $this->purchases->createRequest($this->admin(), [1 => ['quantity' => 5, 'estimated_unit_cost' => 1]]);
            $this->fail('Quản trị viên không được lập yêu cầu nhập');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('do nhân viên lập', $e->getMessage());
        }
        try {
            $this->purchases->draftFromLowStock($this->admin());
            $this->fail('Quản trị viên không được lập yêu cầu nhập (kể cả từ cây dưới ngưỡng)');
        } catch (BusinessException) {
        }
        $this->assertSame(0, PurchaseRequest::count());

        $this->actingAs($this->admin())->get(route('admin.purchase-requests.create'))->assertRedirect(route('admin.purchase-requests.index'));
        $this->get(route('admin.purchase-requests.index'))->assertDontSee('Lập yêu cầu</a>', false);
        $this->actingAs($this->staff)->get(route('admin.purchase-requests.create'))->assertOk();
    }

    public function test_four_eyes_rule_in_service_and_database(): void
    {
        $pr = $this->purchases->createRequest($this->staff, [1 => ['quantity' => 5, 'estimated_unit_cost' => 1]]);
        $this->purchases->transition($pr, PR::Submitted, $this->staff);
        try {
            $this->purchases->approve($pr, $this->staff);
            $this->fail('Người lập không được tự duyệt');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('4 mắt', $e->getMessage());
        }
        $this->expectException(QueryException::class);                        // lưới an toàn ở DB
        DB::table('purchase_requests')->where('id', $pr->id)->update(['approved_by' => $this->staff->id]);
    }

    public function test_approver_can_reduce_quantities_but_not_approve_nothing(): void
    {
        $pr = $this->approvedRequest([1 => 20, 3 => 10], []);                  // duyệt đủ
        $this->assertSame([20, 10], $pr->items()->orderBy('plant_id')->pluck('quantity_approved')->all());

        $pr2 = $this->purchases->createRequest($this->staff, [1 => ['quantity' => 5, 'estimated_unit_cost' => 1], 3 => ['quantity' => 5, 'estimated_unit_cost' => 1]]);
        $this->purchases->transition($pr2, PR::Submitted, $this->staff);
        $ids = $pr2->items()->orderBy('plant_id')->pluck('id')->all();
        try {
            $this->purchases->approve($pr2, $this->admin(), [$ids[0] => 0, $ids[1] => 0]);
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('Từ chối', $e->getMessage());
        }
        $this->purchases->approve($pr2, $this->admin(), [$ids[0] => 2, $ids[1] => 0]);
        $this->expectExceptionMessage('không có trong yêu cầu');              // dòng bị duyệt 0 vẫn trong PR nhưng remaining = 0
        $this->purchases->createReceipt($this->staff, $this->supplier(), $pr2->refresh(), [5 => ['quantity_received' => 1, 'unit_cost' => 1]]);
    }

    public function test_receipt_rules(): void
    {
        $pr = $this->approvedRequest([1 => 5]);
        foreach ([
            'vượt số còn phải nhận' => [1 => ['quantity_received' => 6, 'unit_cost' => 1]],
            'không có trong yêu cầu' => [3 => ['quantity_received' => 1, 'unit_cost' => 1]],
            'ít nhất 1 dòng' => [1 => ['quantity_received' => 0, 'unit_cost' => 1]],
        ] as $msg => $lines) {
            try {
                $this->purchases->createReceipt($this->staff, $this->supplier(), $pr, $lines);
                $this->fail("Phải chặn: {$msg}");
            } catch (BusinessException $e) {
                $this->assertStringContainsString($msg, $e->getMessage());
            }
        }

        $draftPr = $this->purchases->createRequest($this->staff, [1 => ['quantity' => 5, 'estimated_unit_cost' => 1]]);
        $this->expectExceptionMessage('không lập phiếu nhập được');
        $this->purchases->createReceipt($this->staff, $this->supplier(), $draftPr, [1 => ['quantity_received' => 1, 'unit_cost' => 1]]);
    }

    public function test_two_draft_receipts_cannot_both_receive_the_full_remaining(): void
    {
        $pr = $this->approvedRequest([1 => 5]);
        $a = $this->purchases->createReceipt($this->staff, $this->supplier(), $pr, [1 => ['quantity_received' => 5, 'unit_cost' => 1]]);
        $b = $this->purchases->createReceipt($this->staff, $this->supplier(), $pr, [1 => ['quantity_received' => 5, 'unit_cost' => 1]]);
        $this->purchases->completeReceipt($a);
        try {
            $this->purchases->completeReceipt($b);
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('Yêu cầu', $e->getMessage());   // PR đã "đã nhận đủ" -> không nhận thêm
        }
        $this->assertSame(35, $this->stock(1));
        $this->purchases->cancelReceipt($b);
        $this->assertSame(GoodsReceiptStatus::Cancelled, $b->refresh()->status);
    }

    public function test_cancel_request_blocked_while_draft_receipt_exists_and_close_short(): void
    {
        $pr = $this->approvedRequest([1 => 10]);
        $draft = $this->purchases->createReceipt($this->staff, $this->supplier(), $pr, [1 => ['quantity_received' => 4, 'unit_cost' => 1]]);
        try {
            $this->purchases->transition($pr, PR::Cancelled, $this->staff, 'Đổi NCC');
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('phiếu nhập nháp', $e->getMessage());
        }
        $this->purchases->completeReceipt($draft);
        $this->assertSame(PR::PartiallyReceived, $pr->refresh()->status);

        try {
            $this->purchases->transition($pr, PR::Received, $this->staff);
            $this->fail('Đóng yêu cầu phải có lý do');
        } catch (BusinessException) {
        }
        $this->purchases->transition($pr, PR::Received, $this->staff, 'NCC hết hàng');
        $this->assertSame([PR::Received, 'NCC hết hàng'], [$pr->refresh()->status, $pr->rejected_reason]);
        $this->expectExceptionMessage('không lập phiếu nhập được');            // phiếu mới cho PR đã đóng
        $this->purchases->createReceipt($this->staff, $this->supplier(), $pr, [1 => ['quantity_received' => 1, 'unit_cost' => 1]]);
    }

    public function test_ad_hoc_receipt_without_request_and_low_stock_draft(): void
    {
        $r = $this->purchases->createReceipt($this->staff, $this->supplier(), null, [16 => ['quantity_received' => 4, 'unit_cost' => 5000000]]);
        $this->purchases->completeReceipt($r);
        $this->assertSame(5, $this->stock(16));

        $this->place(cart: [3 => $this->stock(3) - 2]);         // bán gần hết: cây #3 xuống dưới ngưỡng
        $pr = $this->purchases->draftFromLowStock($this->staff);
        $this->assertSame(PR::Draft, $pr->status);
        $this->assertTrue($pr->items()->where('plant_id', 3)->exists());
        $this->assertLedgerConsistent();
    }
}

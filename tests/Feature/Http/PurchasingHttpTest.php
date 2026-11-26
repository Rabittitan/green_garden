<?php

namespace Tests\Feature\Http;

use App\Enums\PurchaseRequestStatus as PR;
use App\Models\GoodsReceipt;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class PurchasingHttpTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_purchasing_flow_over_http_with_roles(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff);
        foreach (['admin.suppliers.index', 'admin.purchase-requests.index', 'admin.purchase-requests.create', 'admin.goods-receipts.index', 'admin.goods-receipts.create'] as $r) {
            $this->get(route($r))->assertOk();
        }

        $this->post(route('admin.suppliers.store'), ['code' => 'NCC-009', 'name' => 'NCC Mới'])->assertSessionHas('success');
        $this->post(route('admin.purchase-requests.store'), ['priority' => 'urgent', 'reason' => 'Tết',
            'items' => [1 => ['quantity' => 12, 'estimated_unit_cost' => 140000], 3 => ['quantity' => '']]])->assertRedirect();
        $pr = PurchaseRequest::sole();
        $this->assertSame(1, $pr->items()->count());
        $this->get(route('admin.purchase-requests.show', $pr))->assertOk()->assertSee($pr->code);
        $this->get(route('admin.purchase-requests.edit', $pr))->assertOk();
        $this->post(route('admin.purchase-requests.transition', $pr), ['to' => 'submitted'])->assertSessionHas('success');

        $this->post(route('admin.purchase-requests.approve', $pr))->assertNoAccess();   // nhân viên không duyệt
        $this->actingAs($this->admin())->get(route('admin.purchase-requests.show', $pr))->assertSee('Duyệt (theo số');
        $item = $pr->items()->sole();
        $this->post(route('admin.purchase-requests.approve', $pr), ['approved' => [$item->id => 10]])->assertSessionHas('success');
        $this->assertSame([PR::Approved, 10], [$pr->refresh()->status, $item->refresh()->quantity_approved]);

        $this->actingAs($staff)->post(route('admin.purchase-requests.order', $pr), ['supplier_id' => Supplier::where('code', 'NCC-009')->value('id')])->assertSessionHas('success');
        $this->get(route('admin.goods-receipts.create', ['purchase_request' => $pr->id]))->assertOk()->assertSee('Còn phải nhận');
        $this->post(route('admin.goods-receipts.store'), ['supplier_id' => $pr->refresh()->supplier_id, 'purchase_request_id' => $pr->id,
            'lines' => [1 => ['quantity_received' => 10, 'quantity_rejected' => 1, 'unit_cost' => 140000]]])->assertRedirect();
        $gr = GoodsReceipt::sole();
        $this->get(route('admin.goods-receipts.show', $gr))->assertOk()->assertSee('Nhập kho');
        $this->post(route('admin.goods-receipts.complete', $gr))->assertSessionHas('success');
        $this->assertSame([40, PR::Received], [$this->stock(1), $pr->refresh()->status]);
        $this->get(route('admin.inventory.show', 1))->assertOk()->assertSee($gr->code);
    }

    public function test_low_stock_shortcut_and_validation(): void
    {
        $this->actingAs($this->admin());                                                // quản trị viên chỉ duyệt, không lập
        $this->post(route('admin.purchase-requests.from-low-stock'))->assertSessionHas('error');
        $this->assertSame(0, PurchaseRequest::count());
        $this->actingAs(User::factory()->staff()->create());
        $this->post(route('admin.purchase-requests.from-low-stock'))->assertRedirect();     // cây #16 tồn 1 <= ngưỡng 5
        $this->assertSame(1, PurchaseRequest::count());
        $this->post(route('admin.purchase-requests.store'), ['priority' => 'normal', 'items' => [1 => ['quantity' => 0]]])->assertSessionHas('error');
        $this->post(route('admin.suppliers.store'), ['code' => 'NCC-001', 'name' => 'Trùng'])->assertSessionHasErrors('code');
    }
}

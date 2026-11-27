<?php

namespace Tests\Feature\Http;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\DefectReport;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\Plant;
use App\Models\PlantUnit;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class AdminFlowTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_access_control(): void
    {
        $this->actingAs($this->customer())->get(route('admin.dashboard'))->assertNoAccess();
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.coupons.index'))->assertNoAccess();
        $this->actingAs($staff)->get(route('admin.tiers.index'))->assertNoAccess();
        $staff->forceFill(['is_active' => false])->save();
        $this->actingAs($staff)->get(route('admin.orders.index'))->assertRedirect(route('login'));   // bị khóa: đăng xuất luôn
        $this->assertGuest();
    }

    public function test_every_admin_page_renders(): void
    {
        $order = $this->place(cart: [1 => 1], coupon: null);
        $this->actingAs($this->admin());
        foreach ([
            route('admin.dashboard'), route('admin.orders.index'), route('admin.orders.index', ['status' => 'pending', 'q' => 'GL']),
            route('admin.orders.show', $order), route('admin.plants.index'), route('admin.plants.create'), route('admin.plants.edit', 1),
            route('admin.inventory.index'), route('admin.inventory.index', ['low' => 1]), route('admin.inventory.show', 1),
            route('admin.coupons.index'), route('admin.coupons.create'), route('admin.coupons.edit', Coupon::first()),
            route('admin.customers.index'), route('admin.customers.show', $this->customer()), route('admin.tiers.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_cod_order_processing_via_admin(): void
    {
        $order = $this->place();
        $this->actingAs($this->admin());
        $this->post(route('admin.orders.ship', $order), ['shipping_carrier' => 'GHN', 'tracking_code' => 'X', 'unit_codes' => $this->packCodes($order)])
            ->assertSessionHas('error');                                                                        // chưa xác nhận
        $this->post(route('admin.orders.confirm', $order))->assertSessionHas('success');
        $this->post(route('admin.orders.ship', $order), ['shipping_carrier' => 'GHN'])->assertSessionHasErrors('tracking_code');
        $this->post(route('admin.orders.ship', $order), ['shipping_carrier' => 'GHN', 'tracking_code' => 'GHN1'])->assertSessionHasErrors('unit_codes');   // chưa quét chậu
        $this->post(route('admin.orders.ship', $order), ['shipping_carrier' => 'GHN', 'tracking_code' => 'GHN1', 'unit_codes' => $this->packCodes($order)])->assertSessionHas('success');
        $this->post(route('admin.orders.complete', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame([OrderStatus::Completed, PaymentStatus::Paid], [$order->status, $order->payment_status]);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Hoàn thành')->assertSee('GHN1');
    }

    public function test_return_and_refund_via_admin(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->ipn($order->payments()->sole());
        $this->actingAs($this->admin());
        $this->post(route('admin.orders.confirm', $order));
        $this->post(route('admin.orders.ship', $order), ['shipping_carrier' => 'GHN', 'tracking_code' => 'G2', 'unit_codes' => $this->packCodes($order)]);
        $this->post(route('admin.orders.returning', $order), ['reason' => 'Khách không nhận'])->assertSessionHas('success');
        $item = $order->items()->sole();
        $this->post(route('admin.orders.receive-return', $order), [
            'items' => [$item->id => ['good' => 1, 'damaged' => 0]], 'refund_amount' => 250000, 'note' => 'Khách bom hàng, trừ ship',
        ])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame([OrderStatus::Returned, PaymentStatus::RefundPending], [$order->status, $order->payment_status]);
        $refund = $order->refunds()->sole();
        $this->post(route('admin.payments.confirm-refund', $refund), ['provider_ref' => 'VNP-RF'])->assertSessionHas('success');
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->refresh()->payment_status);
        $this->assertSame(30, $this->stock(1));
    }

    public function test_bank_transfer_confirmation_via_admin(): void
    {
        $order = $this->place(method: PaymentMethod::BankTransfer);
        $this->actingAs($this->admin());
        $this->post(route('admin.orders.confirm-transfer', $order), ['amount' => 1000, 'bank_ref' => 'FT1'])->assertSessionHas('error');
        $this->post(route('admin.orders.confirm-transfer', $order), ['amount' => 280000, 'bank_ref' => 'FT1'])->assertSessionHas('success');
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
    }

    public function test_plant_create_with_opening_stock_and_update_cannot_touch_stock(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.plants.store'), ['category_id' => 1, 'name' => 'Cây Mới', 'price' => 99000, 'reorder_point' => 3,
            'light' => 'ua_bong', 'element' => 'moc', 'opening_stock' => 12, 'cost_price' => 50000, 'is_active' => 1])->assertRedirect();
        $plant = Plant::where('name', 'Cây Mới')->sole();
        $this->assertSame([12, 50000], [$plant->stock, $plant->cost_price]);
        $this->assertMatchesRegularExpression('/^GL\d{5}$/', $plant->sku);
        $this->assertSame('opening', StockMovement::where('plant_id', $plant->id)->sole()->type->value);

        $this->put(route('admin.plants.update', $plant), ['category_id' => 1, 'name' => 'Cây Mới 2', 'price' => 99000, 'reorder_point' => 3,
            'light' => 'ua_bong', 'element' => 'moc', 'stock' => 999, 'cost_price' => 1, 'is_active' => 0])->assertRedirect();
        $plant->refresh();
        $this->assertSame([12, 50000, 'Cây Mới 2', false], [$plant->stock, $plant->cost_price, $plant->name, $plant->is_active]);

        $this->delete(route('admin.plants.destroy', $plant))->assertSessionHas('success');
        $this->assertSoftDeleted($plant);
        $this->assertLedgerConsistent();
    }

    public function test_defect_report_flow_and_stock_count_link(): void
    {
        // Nhân viên: lập phiếu báo lỗi được, không thấy / không vào được phiếu kiểm kê (việc của quản trị viên)
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.inventory.show', 1))->assertOk()
            ->assertSee('Lập phiếu báo lỗi')->assertDontSee(route('admin.stock-counts.index'), false);
        $this->get(route('admin.stock-counts.index'))->assertNoAccess();
        $codes = PlantUnit::where('plant_id', 1)->where('status', 'in_stock')->orderBy('id')->take(2)->pluck('code');
        $this->post(route('admin.defect-reports.store'), ['lines' => [$codes[0] => ['type' => 'dead'], $codes[1] => ['type' => 'pest', 'note' => 'Rệp']]])
            ->assertRedirect();
        $report = DefectReport::sole();
        $this->assertSame(30, $this->stock(1));                                            // chưa duyệt: kho chưa đổi
        $this->post(route('admin.defect-reports.approve', $report))->assertNoAccess();

        $this->actingAs($this->admin());
        $this->get(route('admin.defect-reports.show', $report))->assertOk()->assertSee('Duyệt &amp; xuất hủy 2 chậu', false);
        $this->post(route('admin.defect-reports.approve', $report))->assertSessionHas('success');
        $this->assertSame(28, $this->stock(1));
        $this->get(route('admin.inventory.show', 1))->assertOk()->assertSee('Phiếu kiểm kê')->assertSee('Hư hỏng')->assertSee('Phiếu báo lỗi');
        $this->assertLedgerConsistent();
    }

    public function test_coupon_crud_and_lock_after_use(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.coupons.store'), ['code' => 'sale20', 'name' => 'Sale', 'type' => 'percent', 'value' => 150,
            'min_order_amount' => 0, 'scope' => 'order', 'usage_limit_per_user' => 1])->assertSessionHasErrors('value');
        $this->post(route('admin.coupons.store'), ['code' => 'sale20', 'name' => 'Sale', 'type' => 'percent', 'value' => 20,
            'min_order_amount' => 0, 'scope' => 'categories', 'category_ids' => [1], 'usage_limit_per_user' => 1,
            'combinable_with_tier' => 1, 'is_active' => 1])->assertRedirect(route('admin.coupons.index'));
        $coupon = Coupon::where('code', 'SALE20')->sole();
        $this->assertSame([1], $coupon->categories()->pluck('categories.id')->all());

        $this->place(cart: [1 => 1], coupon: 'SALE20');
        $this->actingAs($this->admin())->put(route('admin.coupons.update', $coupon), ['code' => 'SALE20', 'name' => 'Đổi tên', 'type' => 'fixed',
            'value' => 999999, 'min_order_amount' => 0, 'scope' => 'categories', 'usage_limit_per_user' => 1, 'is_active' => 1])->assertRedirect();
        $coupon->refresh();
        $this->assertSame(['Đổi tên', 'percent', 20], [$coupon->name, $coupon->type->value, $coupon->value]);   // luật giảm giá bị khóa
        $this->get(route('admin.coupons.edit', $coupon))->assertOk()->assertSee('chỉ đổi được');
    }

    public function test_manual_tier_and_tier_config(): void
    {
        $this->actingAs($this->admin());
        $gold = MembershipTier::where('code', 'gold')->first();
        $this->post(route('admin.customers.tier', $this->customer()), ['tier_id' => $gold->id])->assertSessionHasErrors('note');
        $this->post(route('admin.customers.tier', $this->customer()), ['tier_id' => $gold->id, 'note' => 'Khách sỉ'])->assertSessionHas('success');
        $this->assertSame('gold', $this->customer()->tier->code);

        $tiers = MembershipTier::ordered()->get()->mapWithKeys(fn ($t) => [$t->id => [
            'name' => $t->name, 'min_spend' => $t->min_spend, 'discount_percent' => $t->discount_percent,
            'max_discount_per_order' => $t->max_discount_per_order, 'free_shipping_min_order' => $t->free_shipping_min_order,
        ]])->all();
        $bad = $tiers;
        $bad[$gold->id]['min_spend'] = 1000;                               // nhỏ hơn hạng Bạc
        $this->put(route('admin.tiers.update'), ['tiers' => $bad])->assertSessionHas('error');
        $tiers[$gold->id]['min_spend'] = 12_000_000;
        $tiers[$gold->id]['discount_percent'] = 6;
        $this->put(route('admin.tiers.update'), ['tiers' => $tiers])->assertSessionHas('success');
        $this->assertSame([12_000_000, 6], [$gold->fresh()->min_spend, $gold->fresh()->discount_percent]);
    }

    public function test_customer_lock_blocks_login(): void
    {
        $this->actingAs($this->admin())->post(route('admin.customers.toggle', $this->customer()))->assertSessionHas('success');
        $this->assertFalse($this->customer()->is_active);
        $this->actingAs($this->admin())->post(route('admin.customers.toggle', $this->admin()))
            ->assertSessionHas('error', 'Tài khoản nhân viên / quản trị được khóa ở trang Nhân viên.');   // báo rõ thay vì "không có quyền"
        $this->assertTrue($this->admin()->refresh()->is_active);
    }

    public function test_console_commands(): void
    {
        $this->place(method: PaymentMethod::Vnpay);
        $this->travel(20)->minutes();
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('Đã hủy 1')->assertSuccessful();
        $this->artisan('membership:record-spend')->assertSuccessful();
        $this->artisan('shop:reconcile')->assertSuccessful();
        $this->assertSame(OrderStatus::Cancelled, Order::sole()->status);
    }
}

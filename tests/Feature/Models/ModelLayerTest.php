<?php

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StockMovementType;
use App\Enums\TierChangeReason;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/** Model khớp schema: cast Enum, không ghi cột sinh, guard nghiệp vụ, quan hệ. */
class ModelLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_gets_base_tier_and_initial_history(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Customer, $user->fresh()->role);
        $this->assertSame('member', $user->tier->code);
        $this->assertSame(0, $user->fresh()->lifetime_spend);
        $history = $user->tierHistories()->sole();
        $this->assertSame(TierChangeReason::Initial, $history->reason);
    }

    public function test_role_and_tier_cannot_be_mass_assigned(): void
    {
        $gold = MembershipTier::where('code', 'gold')->first();
        $user = User::factory()->create();
        $user->update(['role' => 'admin', 'membership_tier_id' => $gold->id, 'lifetime_spend' => 99999999, 'name' => 'Đổi tên']);

        $user->refresh();
        $this->assertSame('Đổi tên', $user->name);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertSame('member', $user->tier->code);
        $this->assertSame(0, $user->lifetime_spend);
    }

    public function test_tier_lookup_by_spend(): void
    {
        $this->assertSame('member', MembershipTier::forSpend(0)->code);
        $this->assertSame('member', MembershipTier::forSpend(2_999_999)->code);
        $this->assertSame('silver', MembershipTier::forSpend(3_000_000)->code);
        $this->assertSame('gold', MembershipTier::forSpend(29_999_999)->code);
        $this->assertSame('diamond', MembershipTier::forSpend(500_000_000)->code);
        $this->assertSame('silver', MembershipTier::base()->next()->code);
        $this->assertNull(MembershipTier::where('code', 'diamond')->first()->next());
    }

    public function test_plant_stock_cannot_be_changed_outside_inventory_ledger(): void
    {
        $plant = Plant::first();

        $plant->update(['price' => 260000, 'stock' => 999]);            // stock không fillable -> bị bỏ qua
        $this->assertSame(260000, $plant->fresh()->price);
        $this->assertNotSame(999, $plant->fresh()->stock);

        $plant->stock = 999;                                             // gán thẳng -> bị chặn
        $this->expectException(LogicException::class);
        $plant->save();
    }

    public function test_plant_cannot_be_created_with_stock_outside_ledger(): void
    {
        $this->expectException(LogicException::class);
        $p = new Plant(['category_id' => 1, 'sku' => 'NEW1', 'name' => 'Mới', 'slug' => 'moi', 'price' => 1000, 'light' => 'ua_bong', 'element' => 'kim']);
        $p->stock = 5;
        $p->save();
    }

    public function test_ledger_context_allows_stock_change(): void
    {
        $plant = Plant::first();
        Plant::writingLedger(function () use ($plant) {
            $plant->stock = $plant->stock + 1;
            $plant->save();
        });
        $this->assertSame(31, $plant->fresh()->stock);
    }

    public function test_stock_movement_is_append_only(): void
    {
        $m = StockMovement::first();
        $this->assertSame(StockMovementType::Opening, $m->type);
        $this->expectException(LogicException::class);
        $m->update(['note' => 'sửa']);
    }

    public function test_order_item_and_payment_persist_without_touching_generated_columns(): void
    {
        $user = User::where('email', 'khach@greenlife.test')->first();
        $address = $user->defaultAddress;
        $this->assertInstanceOf(UserAddress::class, $address);

        $order = Order::create(array_merge($address->toShippingSnapshot(), [
            'code' => Order::newCode(), 'user_id' => $user->id, 'checkout_token' => (string) Str::uuid(),
            'status' => OrderStatus::PendingPayment, 'membership_tier_id' => $user->membership_tier_id,
            'subtotal' => 250000, 'shipping_fee' => 30000, 'total' => 280000,
            'payment_method' => PaymentMethod::Vnpay, 'payment_expires_at' => now()->addMinutes(15),
        ]));
        $item = $order->items()->create([
            'plant_id' => 1, 'plant_name' => 'Cây Kim Tiền', 'plant_sku' => 'GL00001',
            'unit_price' => 125000, 'quantity' => 2, 'unit_cost' => 75000,
        ]);
        $payment = $order->payments()->create([
            'type' => PaymentType::Charge, 'method' => PaymentMethod::Vnpay, 'amount' => 280000, 'txn_ref' => $order->code.'-1',
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(PaymentMethod::Vnpay, $order->payment_method);
        $this->assertSame(250000, $item->fresh()->line_subtotal);          // cột sinh do MySQL tính
        $this->assertSame(250000, $item->fresh()->line_total);
        $this->assertSame(PaymentState::Pending, $payment->fresh()->status);
        $this->assertTrue($order->pendingCharge()->is($payment));
        $this->assertSame(1, $order->charges()->count());
        $this->assertSame(0, $order->refunds()->count());
        $this->assertFalse($order->isPaymentExpired());
        $this->assertMatchesRegularExpression('/^GL\d{6}[2-9A-HJ-NP-Z]{6}$/', $order->code);
        $this->assertLessThanOrEqual(20, strlen($order->code));
        $this->assertStringContainsString('Sài Gòn', $order->full_address);
    }

    public function test_spend_contribution_excludes_shipping_and_refunds(): void
    {
        $order = new Order(['subtotal' => 500000, 'member_discount' => 25000, 'coupon_discount' => 0,
            'shipping_fee' => 30000, 'shipping_discount' => 10000, 'total' => 495000,
            'paid_amount' => 495000, 'refunded_amount' => 100000]);

        $this->assertSame(475000, $order->merchandise_total);
        $this->assertSame(20000, $order->net_shipping_fee);
        $this->assertSame(395000, $order->refundable_amount);
        $this->assertSame(375000, $order->spend_contribution);           // 495k - 100k - 20k ship
    }

    public function test_coupon_code_is_normalized_and_tier_relation(): void
    {
        $coupon = Coupon::where('code', 'GIAM10')->first();
        $this->assertSame('silver', $coupon->minTier->code);
        $this->assertFalse($coupon->combinable_with_tier);
        $this->assertSame('ABC', Coupon::normalizeCode('  abc '));
        $this->assertFalse($coupon->isLocked());
    }

    public function test_category_descendants_include_children(): void
    {
        $parent = Category::first();
        $child = Category::create(['name' => 'Con', 'slug' => 'con', 'parent_id' => $parent->id]);
        $grandChild = Category::create(['name' => 'Cháu', 'slug' => 'chau', 'parent_id' => $child->id]);

        $ids = Category::withDescendantIds([$parent->id]);
        sort($ids);
        $this->assertSame([$parent->id, $child->id, $grandChild->id], $ids);
    }

    public function test_payment_cannot_mass_assign_generated_keys(): void
    {
        $payment = new Payment(['pending_charge_key' => 1, 'success_charge_key' => 1, 'amount' => 1]);
        $this->assertArrayNotHasKey('pending_charge_key', $payment->getAttributes());
        $this->assertArrayNotHasKey('success_charge_key', $payment->getAttributes());
    }
}

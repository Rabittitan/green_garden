<?php

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;
use App\Exceptions\CouponNotApplicableException;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\Plant;
use App\Models\User;
use App\Services\Pricing\Quote;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Seed: cây #1 Kim Tiền 250k (danh mục 1), #3 Lưỡi Hổ 120k (danh mục 2), #5 Sen Đá 45k (danh mục 3).
 * Mã: CHAOBAN (fixed 50k, đơn ≥300k, đơn đầu), GIAM10 (10% max 100k, hạng Bạc+, KHÔNG cộng dồn), FREESHIP (đơn ≥150k).
 * Ship 30k, miễn ship từ 500k (Bạc 400k, Vàng 300k, Kim cương mọi đơn).
 */
class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricing;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(PricingService::class);
        $this->user = User::where('email', 'khach@greenlife.test')->first();
    }

    private function asTier(string $code): User
    {
        $this->user->forceFill(['membership_tier_id' => MembershipTier::where('code', $code)->value('id')])->save();

        return $this->user->refresh();
    }

    private function coupon(array $attrs): Coupon
    {
        return Coupon::create(array_merge(['code' => 'T'.Str::upper(Str::random(6)), 'name' => 'test', 'type' => 'percent', 'value' => 10], $attrs));
    }

    // ------------------------------------------------------------- không mã

    public function test_base_tier_pays_shipping_under_threshold(): void
    {
        $q = $this->pricing->quoteCart($this->user, [1 => 1]);
        $this->assertSame([250000, 0, 0, 30000, 0, 280000],
            [$q->subtotal(), $q->memberDiscount(), $q->couponDiscount(), $q->shippingFee, $q->shippingDiscount, $q->total()]);
    }

    public function test_free_shipping_from_threshold(): void
    {
        $q = $this->pricing->quoteCart($this->user, [1 => 2]);
        $this->assertSame(0, $q->shippingFee);
        $this->assertSame(500000, $q->total());
    }

    public function test_gold_member_discount_and_lower_free_shipping_threshold(): void
    {
        $q = $this->pricing->quoteCart($this->asTier('gold'), [1 => 2]);
        $this->assertSame(25000, $q->memberDiscount());              // 5% của 500k
        $this->assertSame(5, $q->memberDiscountPercent);
        $this->assertSame(0, $q->shippingFee);                        // 475k >= 300k (ngưỡng Vàng)
        $this->assertSame(475000, $q->total());
    }

    public function test_member_discount_is_capped_and_allocated_exactly(): void
    {
        $q = $this->pricing->quoteCart($this->asTier('gold'), [1 => 20, 3 => 7, 5 => 3]);   // 5M + 840k + 135k
        $this->assertSame(298750, $q->memberDiscount());             // 5% của 5.975.000 = 298.750 < trần 300k
        $q = $this->pricing->quoteCart($this->user, [1 => 30]);       // 7.5M x 5% = 375k -> trần 300k
        $this->assertSame(300000, $q->memberDiscount());
        $this->assertSame(300000, array_sum(array_map(fn ($l) => $l->memberDiscount, $q->lines)));
    }

    // ------------------------------------------------------------- mã giảm giá

    public function test_non_combinable_coupon_wins_when_better_for_customer(): void
    {
        $q = $this->pricing->quoteCart($this->asTier('silver'), [1 => 2], 'giam10');
        $this->assertSame('GIAM10', $q->coupon->code);
        $this->assertSame(0, $q->memberDiscount());                   // không cộng dồn
        $this->assertSame(50000, $q->couponDiscount());               // 10% của 500k (3% hạng chỉ 15k)
        $this->assertSame(0, $q->shippingFee);                        // 450k >= 400k (ngưỡng Bạc)
        $this->assertSame(450000, $q->total());
    }

    public function test_non_combinable_coupon_loses_to_better_member_discount(): void
    {
        $c = $this->coupon(['type' => 'fixed', 'value' => 5000, 'combinable_with_tier' => false]);
        $q = $this->pricing->quoteCart($this->asTier('silver'), [1 => 2], $c->code);
        $this->assertNull($q->coupon);                                // không tốn lượt dùng mã
        $this->assertSame(15000, $q->memberDiscount());
        $this->assertNotEmpty($q->notices);
    }

    public function test_combinable_coupon_stacks_on_member_discount(): void
    {
        $c = $this->coupon(['value' => 10, 'combinable_with_tier' => true]);
        $q = $this->pricing->quoteCart($this->asTier('gold'), [1 => 2], $c->code);
        $this->assertSame(25000, $q->memberDiscount());
        $this->assertSame(47500, $q->couponDiscount());              // 10% của (500k - 25k)
        $this->assertSame(427500, $q->total());
    }

    public function test_tier_restricted_coupon_is_rejected_for_lower_tier(): void
    {
        $this->expectException(CouponNotApplicableException::class);
        $this->expectExceptionMessage('hạng Bạc trở lên');
        $this->pricing->quoteCart($this->user, [1 => 2], 'GIAM10');
    }

    public function test_first_order_coupon_with_minimum(): void
    {
        $q = $this->pricing->quoteCart($this->user, [1 => 2], 'CHAOBAN');
        $this->assertSame(50000, $q->couponDiscount());
        $this->assertSame(30000, $q->shippingFee);                   // 450k < 500k
        $this->assertSame(480000, $q->total());

        try {
            $this->pricing->quoteCart($this->user, [1 => 1], 'CHAOBAN');
            $this->fail('Phải từ chối đơn dưới mức tối thiểu');
        } catch (CouponNotApplicableException $e) {
            $this->assertStringContainsString('tối thiểu', $e->getMessage());
        }

        $this->makeOrder();
        $this->expectExceptionMessage('đơn hàng đầu tiên');
        $this->pricing->quoteCart($this->user, [1 => 2], 'CHAOBAN');
    }

    public function test_free_shipping_coupon(): void
    {
        $q = $this->pricing->quoteCart($this->user, [1 => 1], 'FREESHIP');
        $this->assertSame([30000, 30000, 250000], [$q->shippingFee, $q->shippingDiscount, $q->total()]);
        $this->assertSame(30000, $q->couponBenefit());
    }

    public function test_coupon_without_any_benefit_is_rejected(): void
    {
        $this->expectException(CouponNotApplicableException::class);
        $this->expectExceptionMessage('không mang lại ưu đãi');
        $this->pricing->quoteCart($this->user, [1 => 2], 'FREESHIP');   // 500k đã miễn ship
    }

    public function test_category_coupon_applies_only_to_category_and_its_children(): void
    {
        $c = $this->coupon(['value' => 20, 'scope' => 'categories']);
        $c->categories()->attach(1);
        $q = $this->pricing->quoteCart($this->user, [1 => 1, 3 => 1], $c->code);
        $this->assertSame(50000, $q->line(1)->couponDiscount);          // 20% của 250k
        $this->assertSame(0, $q->line(3)->couponDiscount);              // Lưỡi Hổ thuộc danh mục 2

        $child = Category::create(['name' => 'Con', 'slug' => 'con', 'parent_id' => 1]);
        Plant::find(3)->update(['category_id' => $child->id]);
        $q = $this->pricing->quoteCart($this->user, [1 => 1, 3 => 1], $c->code);
        $this->assertSame(24000, $q->line(3)->couponDiscount);          // giờ thuộc danh mục con của 1
    }

    public function test_fixed_coupon_never_exceeds_eligible_amount(): void
    {
        $c = $this->coupon(['type' => 'fixed', 'value' => 1000000, 'scope' => 'plants']);
        $c->plants()->attach(5);
        $q = $this->pricing->quoteCart($this->user, [1 => 1, 5 => 1], $c->code);
        $this->assertSame(45000, $q->couponDiscount());
        $this->assertSame(45000, $q->line(5)->couponDiscount);
        $this->assertSame(0, $q->line(5)->total());
    }

    public function test_coupon_validity_rules(): void
    {
        $cases = [
            'ngừng áp dụng' => ['is_active' => false],
            'hết hạn' => ['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()],
            'chưa tới thời gian' => ['starts_at' => now()->addDay()],
        ];
        foreach ($cases as $msg => $attrs) {
            $c = $this->coupon($attrs);
            try {
                $this->pricing->quoteCart($this->user, [1 => 1], $c->code);
                $this->fail("Mã {$msg} phải bị từ chối");
            } catch (CouponNotApplicableException $e) {
                $this->assertStringContainsString($msg, $e->getMessage());
            }
        }

        $c = $this->coupon(['usage_limit' => 1]);
        $c->forceFill(['used_count' => 1])->save();
        $this->expectExceptionMessage('hết lượt');
        $this->pricing->quoteCart($this->user, [1 => 1], $c->code);
    }

    public function test_unknown_coupon_and_inactive_plant(): void
    {
        try {
            $this->pricing->quoteCart($this->user, [1 => 1], 'KHONGCO');
            $this->fail();
        } catch (CouponNotApplicableException $e) {
            $this->assertStringContainsString('không tồn tại', $e->getMessage());
        }
        Plant::find(1)->update(['is_active' => false]);
        $this->expectException(BusinessException::class);
        $this->pricing->quoteCart($this->user, [1 => 1]);
    }

    // ------------------------------------------------------------- bất biến (ngẫu nhiên) + đối chiếu CHECK của DB

    public function test_random_quotes_keep_invariants_and_pass_database_checks(): void
    {
        mt_srand(17112026);
        $plants = Plant::pluck('id')->all();
        $tiers = MembershipTier::pluck('code')->all();
        $coupons = [
            null, 'FREESHIP',
            $this->coupon(['value' => 15, 'max_discount_amount' => 70000])->code,
            $this->coupon(['type' => 'fixed', 'value' => 120000, 'combinable_with_tier' => false])->code,
            tap($this->coupon(['value' => 30, 'scope' => 'categories']), fn ($c) => $c->categories()->attach([1, 3]))->code,
        ];
        $persisted = 0;

        for ($i = 0; $i < 300; $i++) {
            $user = $this->asTier($tiers[array_rand($tiers)]);
            $cart = [];
            foreach ((array) array_rand(array_flip($plants), mt_rand(1, 4)) as $pid) {
                $cart[$pid] = mt_rand(1, 6);
            }
            try {
                $q = $this->pricing->quoteCart($user, $cart, $coupons[array_rand($coupons)]);
            } catch (CouponNotApplicableException) {
                continue;
            }

            $this->assertSame($q->subtotal() - $q->memberDiscount() - $q->couponDiscount() + $q->shippingFee - $q->shippingDiscount, $q->total());
            $this->assertGreaterThanOrEqual(0, $q->total());
            $this->assertLessThanOrEqual($q->shippingFee, $q->shippingDiscount);
            foreach ($q->lines as $l) {
                $this->assertGreaterThanOrEqual(0, $l->total());
            }

            if ($persisted < 60) {                                      // để CHECK của MySQL kiểm lại công thức
                $this->makeOrder($q);
                $persisted++;
            }
        }
        $this->assertSame(60, $persisted);
    }

    private function makeOrder(?Quote $q = null): Order
    {
        $q ??= $this->pricing->quoteCart($this->user, [1 => 1]);

        return Order::create([
            'code' => Order::newCode(), 'user_id' => $this->user->id, 'checkout_token' => (string) Str::uuid(),
            'status' => OrderStatus::Pending, 'recipient_name' => 'K', 'phone' => '0900000002', 'province' => 'HCM',
            'district' => 'Q1', 'ward' => 'BN', 'address_line' => '12', 'membership_tier_id' => $q->tier->id,
            'member_discount_percent' => $q->memberDiscountPercent, 'coupon_id' => $q->coupon?->id, 'coupon_code' => $q->coupon?->code,
            'subtotal' => $q->subtotal(), 'member_discount' => $q->memberDiscount(), 'coupon_discount' => $q->couponDiscount(),
            'shipping_fee' => $q->shippingFee, 'shipping_discount' => $q->shippingDiscount, 'total' => $q->total(),
            'payment_method' => PaymentMethod::Cod,
        ]);
    }
}

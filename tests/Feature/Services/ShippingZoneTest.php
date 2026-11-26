<?php

namespace Tests\Feature\Services;

use App\Exceptions\PriceChangedException;
use App\Models\Plant;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\PricingService;
use App\Services\ShippingZoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Đợt 6: phí ship theo vùng (tỉnh nhận hàng) + khối lượng; giá do quản trị viên đặt. */
class ShippingZoneTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private const HCM = 'Thành phố Hồ Chí Minh';

    private const HN = 'Thành phố Hà Nội';

    private function zones(): void
    {
        $svc = app(ShippingZoneService::class);
        $svc->save(null, ['name' => 'Nội thành', 'base_fee' => 20000, 'base_weight_grams' => 2000, 'extra_fee_per_kg' => 3000, 'is_default' => false, 'sort_order' => 1], [self::HCM]);
        $svc->save(null, ['name' => 'Còn lại', 'base_fee' => 45000, 'base_weight_grams' => 2000, 'extra_fee_per_kg' => 8000, 'is_default' => true, 'sort_order' => 2], []);
    }

    private function fee(array $cart, ?string $province, ?User $user = null): int
    {
        return app(PricingService::class)->quoteCart($user ?? $this->customer(), $cart, null, $province)->shippingFee;
    }

    public function test_without_zones_the_old_flat_fee_applies(): void
    {
        $this->assertSame(30000, $this->fee([5 => 1], self::HN));
        $this->assertSame(30000, $this->fee([5 => 1], null));
    }

    public function test_fee_follows_province_zone_and_package_weight(): void
    {
        $this->zones();
        $this->assertSame(20000, $this->fee([5 => 1], self::HCM));                         // 1 kg mặc định <= 2 kg đầu
        $this->assertSame(20000 + 3 * 3000, $this->fee([5 => 5], self::HCM));             // 5 kg: vượt 3 kg
        $this->assertSame(45000, $this->fee([5 => 1], self::HN));                          // tỉnh chưa gán -> vùng mặc định
        $this->assertSame(45000, $this->fee([5 => 1], null));                              // chưa có địa chỉ -> vùng mặc định
        Plant::whereKey(5)->update(['weight_grams' => 3500]);
        $this->assertSame(45000 + 2 * 8000, $this->fee([5 => 1], self::HN));               // 3,5 kg: vượt 1,5 -> làm tròn 2 kg
        $this->assertSame(0, $this->fee([1 => 2], self::HN));                               // 500.000đ: vẫn miễn ship theo ngưỡng
    }

    public function test_order_uses_the_delivery_province_and_checkout_requotes(): void
    {
        $this->zones();
        $order = $this->place(cart: [5 => 1]);                                             // địa chỉ test ở TP.HCM
        $this->assertSame(20000, $order->shipping_fee);

        $this->actingAs($this->customer());
        session(['cart' => [5 => 1]]);
        $this->get(route('checkout.form'))->assertOk()->assertSee('theo tỉnh nhận hàng + khối lượng');
        $q = $this->getJson(route('checkout.shipping-quote', ['province' => self::HN]))->assertOk()->json();
        $this->assertSame([45000, '45.000đ', 45000 + 45000], [$q['shipping_fee'], $q['shipping_fee_text'], $q['total']]);

        $this->expectException(PriceChangedException::class);                             // khách thấy giá HCM nhưng giao Hà Nội
        $this->place(cart: [5 => 1], expectedTotal: 45000 + 45000);
    }

    public function test_admin_configures_zones_and_provinces_move_between_zones(): void
    {
        $this->zones();
        $this->actingAs($this->admin())->get(route('admin.shipping-zones.index'))->assertOk()->assertSee('Nội thành')->assertSee('Mặc định');
        $this->post(route('admin.shipping-zones.store'), ['name' => 'Miền Bắc', 'base_fee' => 35000, 'base_weight_kg' => 1.5, 'extra_fee_per_kg' => 6000,
            'is_default' => 1, 'provinces' => [self::HN, self::HCM, 'Tỉnh không có']])->assertRedirect(route('admin.shipping-zones.index'));
        $north = ShippingZone::where('name', 'Miền Bắc')->sole();
        $this->assertSame([1500, true], [$north->base_weight_grams, $north->is_default]);
        $this->assertEqualsCanonicalizing([self::HCM, self::HN], $north->provinces()->pluck('province')->all());   // HCM rời vùng cũ, tên lạ bị bỏ
        $this->assertSame(1, ShippingZone::where('is_default', true)->count());             // chỉ 1 vùng mặc định
        $this->assertSame(0, ShippingZone::where('name', 'Nội thành')->sole()->provinces()->count());

        $this->delete(route('admin.shipping-zones.destroy', $north))->assertRedirect();
        $this->assertSame(1, ShippingZone::where('is_default', true)->count());             // vùng khác thành mặc định
        $this->actingAs(User::factory()->staff()->create())->get(route('admin.shipping-zones.index'))->assertForbidden();
    }
}

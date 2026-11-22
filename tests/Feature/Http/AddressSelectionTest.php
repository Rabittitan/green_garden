<?php

namespace Tests\Feature\Http;

use App\Models\Order;
use App\Models\UserAddress;
use App\Support\VnDivisions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** K9: địa chỉ chọn Tỉnh/thành → Phường/xã theo địa giới từ 01/07/2025 (34 tỉnh, không còn cấp huyện). */
class AddressSelectionTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private const HCM = 'Thành phố Hồ Chí Minh';

    /** Vào trang đặt hàng (có hàng trong giỏ) và trả về payload hợp lệ để gửi đơn. */
    private function checkout(array $override = []): array
    {
        $this->actingAs($this->customer());
        session(['cart' => [1 => 1]]);
        $this->get(route('checkout.form'))->assertOk();

        return array_merge($this->shipping(), [
            'payment_method' => 'cod', 'checkout_token' => session('checkout.token'), 'expected_total' => 280000,
        ], $override);
    }

    public function test_bundled_catalogue_is_complete_and_fits_the_columns(): void
    {
        $data = json_decode((string) file_get_contents(public_path(VnDivisions::PATH)), true);
        $this->assertStringContainsString('vietnam-address-database@1.0.0', $data['_meta']['nguon']);
        $this->assertStringContainsString('MIT', $data['_meta']['nguon']);

        $this->assertCount(34, VnDivisions::provinces());
        $all = array_merge(...array_values(VnDivisions::map()));
        $this->assertCount(3321, $all);
        foreach (VnDivisions::map() as $province => $wards) {
            $this->assertSame(count($wards), count(array_unique($wards)), "Trùng tên xã trong {$province}");
            foreach ([$province, ...$wards] as $name) {
                $this->assertLessThanOrEqual(100, mb_strlen($name), $name);              // cột VARCHAR(100)
                $this->assertMatchesRegularExpression('/^(Thành phố |Tỉnh )?[\p{Lu}]/u', $province);
            }
        }
        $this->assertTrue(VnDivisions::hasWard(self::HCM, 'Phường Sài Gòn'));
        $this->assertTrue(VnDivisions::hasWard('An Giang', 'Đặc khu Phú Quốc'));
        $this->assertFalse(VnDivisions::hasWard(self::HCM, 'Phường Ba Đình'));          // thuộc Hà Nội
        $this->assertNotContains('TP. Hồ Chí Minh', VnDivisions::provinces());
    }

    public function test_checkout_shows_selects_prefilled_from_default_address(): void
    {
        $this->actingAs($this->customer());
        session(['cart' => [1 => 1]]);
        $html = $this->get(route('checkout.form'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<select id="f-province".*?</select>#s', $html, $p));
        $this->assertSame(34, substr_count($p[0], '<option value="') - 1);                     // trừ dòng "-- Chọn --"
        $this->assertStringContainsString('<option value="'.self::HCM.'" selected>', $p[0]);
        $this->assertSame(1, preg_match('#<select id="f-ward".*?</select>#s', $html, $w));
        $this->assertSame(count(VnDivisions::wards(self::HCM)), substr_count($w[0], '<option value="') - 1);
        $this->assertStringContainsString('<option value="Phường Sài Gòn" selected>', $w[0]);
        $this->assertStringNotContainsString('name="district"', $html);                         // không còn ô quận/huyện
        $this->assertStringContainsString('js/address.js', $html);
        $this->assertMatchesRegularExpression('#data-gg-address-note\s+hidden#', $html);        // địa chỉ mới: không cảnh báo
    }

    public function test_order_stores_province_as_district_and_shows_no_duplicate(): void
    {
        $this->post(route('checkout.store'), $this->checkout(['district' => 'Quận 1']))->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame([self::HCM, self::HCM, 'Phường Sài Gòn'], [$order->province, $order->district, $order->ward]);
        $this->assertSame('12 Nguyễn Huệ, Phường Sài Gòn, Thành phố Hồ Chí Minh', $order->full_address);
        $this->get(route('orders.show', $order))->assertSee('12 Nguyễn Huệ, Phường Sài Gòn, Thành phố Hồ Chí Minh');
    }

    public function test_province_and_ward_must_come_from_the_catalogue(): void
    {
        $this->post(route('checkout.store'), $this->checkout(['province' => 'TP. Hồ Chí Minh']))
            ->assertSessionHasErrors(['province' => 'Vui lòng chọn tỉnh/thành phố trong danh sách (34 tỉnh/thành từ 01/07/2025).']);
        $this->post(route('checkout.store'), $this->checkout(['ward' => 'Phường Ba Đình']))           // xã của tỉnh khác
            ->assertSessionHasErrors(['ward' => 'Phường/xã không thuộc tỉnh/thành phố đã chọn, vui lòng chọn lại.']);
        $this->post(route('checkout.store'), $this->checkout(['ward' => '']))->assertSessionHasErrors('ward');
        $this->assertSame(0, Order::count());
    }

    public function test_address_book_uses_the_same_rules(): void
    {
        $this->actingAs($this->customer());
        $this->get(route('addresses.index'))->assertOk()->assertSee('id="f-province"', false)->assertDontSee('Quận / Huyện');
        $this->post(route('addresses.store'), array_merge($this->shipping(), ['province' => 'Thành phố Hà Nội', 'ward' => 'Phường Ba Đình',
            'district' => 'Quận Ba Đình', 'address_line' => '1 Hùng Vương']))->assertSessionHasNoErrors();
        $a = UserAddress::where('address_line', '1 Hùng Vương')->sole();
        $this->assertSame(['Thành phố Hà Nội', 'Thành phố Hà Nội'], [$a->province, $a->district]);
        $this->assertSame('1 Hùng Vương, Phường Ba Đình, Thành phố Hà Nội', $a->full_address);

        $this->post(route('addresses.store'), array_merge($this->shipping(), ['province' => 'Hà Nội']))->assertSessionHasErrors('province');
    }

    public function test_old_three_level_address_is_flagged_and_old_orders_still_display(): void
    {
        $user = $this->customer();
        $user->addresses()->update(['is_default' => false]);
        $user->addresses()->create(['recipient_name' => 'K', 'phone' => '0900000002', 'province' => 'TP. Hồ Chí Minh',
            'district' => 'Quận 1', 'ward' => 'Phường Bến Nghé', 'address_line' => '5 Lê Lợi', 'is_default' => true]);
        $this->actingAs($user);
        session(['cart' => [1 => 1]]);
        $html = $this->get(route('checkout.form'))->assertOk()->getContent();
        $this->assertStringContainsString('theo địa giới cũ trước 01/07/2025', $html);
        $this->assertDoesNotMatchRegularExpression('#data-gg-address-note\s+hidden#', $html);  // cảnh báo đang hiện
        $this->assertStringContainsString('Phường Bến Nghé, TP. Hồ Chí Minh', $html);
        $this->assertStringNotContainsString('" selected>Phường', $html);                       // không chọn sẵn xã cũ

        // Đơn cũ 3 cấp giữ nguyên dữ liệu và hiển thị đủ quận
        $old = $this->place();
        $old->forceFill(['province' => 'TP. Hồ Chí Minh', 'district' => 'Quận 1', 'ward' => 'Phường Bến Nghé'])->save();
        $this->assertSame('12 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh', $old->refresh()->full_address);
    }
}

<?php

namespace Tests\Feature\Regression;

use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** H8 — lỗi tìm ra khi nghiệm thu theo tiêu chí C1–C9. */
class H8FindingsTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    /** C6: sổ địa chỉ không phân trang (hiện cả ở trang đặt hàng) -> phải có trần. */
    public function test_address_book_is_capped(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $existing = $user->addresses()->count();
        for ($i = $existing; $i < UserAddress::MAX_PER_USER; $i++) {
            $this->post(route('addresses.store'), array_merge($this->shipping(), ['address_line' => "{$i} Lê Lợi"]))->assertSessionHas('success');
        }
        $this->post(route('addresses.store'), array_merge($this->shipping(), ['address_line' => '999 Lê Lợi']))
            ->assertSessionHas('error');
        $this->assertSame(UserAddress::MAX_PER_USER, $user->addresses()->count());
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    /** Sổ đầy: đặt hàng với địa chỉ mới vẫn thành công, chỉ không lưu thêm vào sổ. */
    public function test_checkout_with_full_address_book_still_places_order(): void
    {
        $user = $this->customer();
        for ($i = $user->addresses()->count(); $i < UserAddress::MAX_PER_USER; $i++) {
            $user->addresses()->create(array_merge($this->shipping(), ['address_line' => "{$i} Pasteur"]));
        }
        $this->actingAs($user)->post(route('cart.add', 1), ['quantity' => 1]);
        $this->get(route('checkout.form'));
        $this->post(route('checkout.store'), array_merge($this->shipping(), [
            'address_line' => '1 Đường Mới', 'payment_method' => 'cod', 'save_address' => 1,
            'checkout_token' => session('checkout.token'), 'expected_total' => 280000,
        ]))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(1, $user->orders()->count());
        $this->assertSame(UserAddress::MAX_PER_USER, $user->addresses()->count());
    }
}

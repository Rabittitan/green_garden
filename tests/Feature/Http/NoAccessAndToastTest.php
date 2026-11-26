<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Góp ý 26/11: vào link không có quyền -> về trang đầu của vai trò; thông báo hiện NỔI thay vì khung đẩy trang. */
class NoAccessAndToastTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_each_role_lands_on_its_own_home_with_a_floating_notice(): void
    {
        // Khách hàng mở trang quản trị -> trang chủ shop
        $this->actingAs($this->customer())->get(route('admin.coupons.index'))->assertNoAccess('home');
        $home = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('<div class="gg-toast error" role="alert" data-toast data-ttl="8000">', $home);
        $this->assertStringContainsString('Bạn không có quyền truy cập trang đó.', $home);

        // Nhân viên có "Thống kê" -> trang Thống kê; nhân viên chỉ có "Đơn hàng" -> thẳng trang Đơn hàng (1 lần chuyển, còn thông báo)
        $this->actingAs(User::factory()->staff()->create())->get(route('admin.staff.index'))->assertNoAccess('admin.dashboard');
        $this->actingAs(User::factory()->staff()->create(['permissions' => ['orders']]))->get(route('admin.coupons.index'))->assertNoAccess('admin.orders.index');
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Bạn không có quyền truy cập trang đó.');
        $this->actingAs(User::factory()->staff()->create(['permissions' => []]))->get(route('admin.plants.index'))->assertNoAccess('admin.dashboard');
    }

    public function test_policy_denials_and_posts_redirect_too_but_json_keeps_403(): void
    {
        $order = $this->place();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('orders.show', $order))->assertNoAccess('home');               // đơn của người khác (policy)
        $this->post(route('orders.cancel', $order), ['reason' => 'x'])->assertNoAccess('home');
        $this->assertSame('pending', $order->refresh()->status->value);                                   // không có tác dụng phụ

        $this->actingAs($this->customer())->getJson(route('admin.units.check', ['code' => 'ABCD2345']))->assertForbidden();
        $this->postJson(route('admin.articles.preview'), ['content' => 'x'])->assertForbidden();
        auth()->logout();
        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));                         // chưa đăng nhập: vẫn tới đăng nhập
    }

    public function test_flash_messages_render_as_toasts_in_both_layouts(): void
    {
        $this->actingAs($this->customer())->post(route('cart.add', 1))->assertSessionHas('success');
        $page = $this->get(route('plants.index'))->getContent();
        $this->assertStringContainsString('class="gg-toast success" role="status"', $page);
        $this->assertStringNotContainsString('alert alert-success', $page);                               // không còn khung đẩy nội dung
        $this->assertStringContainsString('js/toasts.js', $page);

        $this->actingAs($this->admin())->post(route('admin.suppliers.store'), [])->assertSessionHasErrors();
        $admin = $this->get(route('admin.suppliers.index'))->getContent();
        $this->assertStringContainsString('Kiểm tra lại thông tin:', $admin);                             // lỗi nhập liệu gom thành 1 thông báo nổi
        $this->assertStringContainsString('js/toasts.js', $admin);
        $this->assertStringNotContainsString('<div class="alert alert-danger"><ul', $admin);
    }
}

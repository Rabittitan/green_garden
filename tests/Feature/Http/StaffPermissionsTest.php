<?php

namespace Tests\Feature\Http;

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Đợt 2: quản trị viên tạo nhân viên + cấp nhóm quyền; nhân viên chỉ thấy / dùng mục được cấp. */
class StaffPermissionsTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_admin_creates_staff_with_limited_permissions(): void
    {
        $this->actingAs($this->admin())->get(route('admin.staff.index'))->assertOk()->assertSee('Thêm nhân viên');
        $this->post(route('admin.staff.store'), ['name' => 'Bé Na', 'email' => 'NA@shop.vn', 'password' => 'MatKhau2026', 'password_confirmation' => 'MatKhau2026',
            'permissions' => ['orders', 'content', 'khong-co-quyen-nay']])->assertSessionHasErrors('permissions.2');
        $this->post(route('admin.staff.store'), ['name' => 'Bé Na', 'email' => 'NA@shop.vn', 'password' => 'MatKhau2026', 'password_confirmation' => 'MatKhau2026',
            'permissions' => ['content', 'orders']])->assertRedirect(route('admin.staff.index'));

        $na = User::where('email', 'na@shop.vn')->sole();
        $this->assertSame([UserRole::Staff, ['orders', 'content'], true], [$na->role, $na->permissions, $na->is_active]);
        $this->assertNotNull($na->tier);                                                     // bất biến: tài khoản nào cũng có hạng
        $this->assertTrue(Hash::check('MatKhau2026', $na->password));

        $this->actingAs($na);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.orders.index'));     // không có "Thống kê"
        $menu = $this->get(route('admin.orders.index'))->assertOk()->getContent();
        foreach (['Đơn hàng', 'Tin tức', 'Slide trang chủ'] as $seen) {
            $this->assertStringContainsString($seen.'</a>', $menu);
        }
        foreach (['Thống kê</a>', 'Cây cảnh</a>', 'Kho</a>', 'Yêu cầu nhập</a>', 'Đánh giá</a>', 'Nhân viên</a>', 'Mã giảm giá</a>'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $menu);
        }
        $this->assertStringNotContainsString('Kho &amp; nhập hàng', $menu);                // nhóm rỗng: ẩn cả tiêu đề
        $this->get(route('admin.articles.index'))->assertOk();
        $this->get(route('admin.plants.index'))->assertForbidden();
        $this->get(route('admin.inventory.show', 1))->assertForbidden();
        $this->get(route('admin.staff.index'))->assertForbidden();
    }

    public function test_staff_without_any_permission_sees_a_friendly_page(): void
    {
        $none = User::factory()->staff()->create(['permissions' => []]);
        $this->actingAs($none)->get(route('admin.dashboard'))->assertOk()->assertSee('chưa được cấp quyền dùng mục nào');
        $this->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_existing_staff_keep_full_staff_access_but_never_admin_only(): void
    {
        $old = User::factory()->staff()->create();                                          // permissions = NULL
        $this->assertNull($old->permissions);
        foreach (StaffPermission::cases() as $p) {
            $this->assertTrue($old->hasPermission($p));
        }
        $this->actingAs($old)->get(route('admin.dashboard'))->assertOk()->assertSee('Thống kê kinh doanh');
        $this->get(route('admin.coupons.index'))->assertForbidden();
        $this->assertFalse($this->customer()->hasPermission(StaffPermission::Orders));
    }

    public function test_admin_edits_permissions_locks_and_resets_password(): void
    {
        $s = User::factory()->staff()->create(['permissions' => ['orders']]);
        $this->actingAs($this->admin())->get(route('admin.staff.edit', $s))->assertOk()->assertSee('Được dùng các mục');
        $this->put(route('admin.staff.update', $s), ['name' => 'Đổi tên', 'permissions' => ['inventory'], 'is_active' => 1,
            'password' => 'MoiMoi20261', 'password_confirmation' => 'MoiMoi20261'])->assertRedirect(route('admin.staff.index'));
        $s->refresh();
        $this->assertSame(['Đổi tên', ['inventory']], [$s->name, $s->permissions]);
        $this->assertTrue(Hash::check('MoiMoi20261', $s->password));

        $this->put(route('admin.staff.update', $s), ['name' => 'Đổi tên', 'is_active' => 0]);    // khóa + bỏ hết quyền
        $s->refresh();
        $this->assertSame([false, []], [$s->is_active, $s->permissions]);
        $this->actingAs($s)->get(route('admin.inventory.index'))->assertRedirect(route('login'));   // bị đăng xuất ngay

        // Không sửa tài khoản quản trị viên qua trang này
        $this->actingAs($this->admin())->get(route('admin.staff.edit', $this->admin()))->assertRedirect(route('admin.staff.index'));
        $this->put(route('admin.staff.update', $this->admin()), ['name' => 'x', 'is_active' => 0])->assertSessionHas('error');
        $this->assertTrue($this->admin()->refresh()->is_active);
    }
}

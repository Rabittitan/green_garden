<?php

namespace Tests\Feature\Audit;

use App\Enums\PaymentMethod;
use App\Enums\StaffPermission;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Coupon;
use App\Models\CouponGiftRule;
use App\Models\FlashSale;
use App\Models\HomeSlide;
use App\Models\Order;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\CommentService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * H5 — Ma trận phân quyền (tiêu chí C5). Tự đọc MỌI route admin từ router nên route mới thêm sau này
 * cũng tự động được kiểm: khách vãng lai -> đăng nhập, khách hàng -> 403, nhân viên -> 403 ở route chỉ dành cho quản trị.
 */
class RoutePermissionMatrixTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    /** @return array<string,mixed> tên tham số route => giá trị */
    private function fixtures(): array
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $staff = User::factory()->staff()->create();
        $pr = app(PurchaseService::class)->createRequest($staff, [1 => ['quantity' => 3, 'estimated_unit_cost' => 1]]);
        $comment = app(CommentService::class)->post($this->customer(), Plant::find(1), 'Hỏi thử');
        $receipt = app(PurchaseService::class)->createReceipt($staff, Supplier::first(), null, [1 => ['quantity_received' => 1, 'unit_cost' => 1]]);
        $review = new PlantReview(['plant_id' => 1, 'user_id' => $this->customer()->id, 'order_item_id' => $order->items()->sole()->id, 'rating' => 5]);
        $review->save();

        return [
            'order' => $order->id, 'plant' => 1, 'id' => 1, 'coupon' => Coupon::first()->id,
            'user' => User::factory()->create()->id,                       // khách riêng: route khóa / đổi hạng không ảnh hưởng khách đang thử quyền
            'payment' => $order->payments()->sole()->id, 'purchase_request' => $pr->id, 'supplier' => Supplier::first()->id,
            'goods_receipt' => $receipt->id, 'comment' => $comment->id, 'review' => $review->id, 'address' => UserAddress::first()->id, 'article' => Article::first()->id, 'slide' => HomeSlide::first()->id, 'stockCount' => tap(new StockCount(['code' => 'KK-TEST-0001']), fn ($c) => $c->forceFill(['created_by' => User::where('role', 'admin')->first()->id, 'status' => 'draft'])->save())->id, 'flashSale' => tap(new FlashSale(['name' => 'FS', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]), fn ($f) => $f->forceFill(['created_by' => User::factory()->staff()->create()->id, 'status' => 'submitted'])->save())->id, 'rule' => CouponGiftRule::create(['name' => 'Quà', 'min_order_total' => 1, 'type' => 'fixed', 'value' => 1000, 'valid_days' => 7])->id, 'staff' => User::factory()->staff()->create()->id,
        ];
    }

    /** @return list<RouteDef> */
    private function adminRoutes(): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), fn (RouteDef $r) => str_starts_with((string) $r->getName(), 'admin.')));
    }

    private function url(RouteDef $r, array $fx): string
    {
        $params = [];
        foreach ($r->parameterNames() as $p) {
            $params[$p] = $fx[$p] ?? $this->fail("Thiếu fixture cho tham số {$p} của route {$r->getName()}");
        }

        return route($r->getName(), $params);
    }

    public function test_every_admin_route_is_protected_by_role(): void
    {
        $fx = $this->fixtures();
        $staff = User::factory()->staff()->create();
        $checked = 0;

        foreach ($this->adminRoutes() as $r) {
            $mw = $r->gatherMiddleware();
            $this->assertContains('admin', $mw, "Route {$r->getName()} thiếu middleware admin");
            $adminOnly = in_array('admin:admin', $mw, true);
            $method = $r->methods()[0];
            $url = $this->url($r, $fx);

            auth()->logout();
            $this->call($method, $url)->assertRedirect(route('login'));
            $this->actingAs($this->customer())->call($method, $url)->assertForbidden();

            $staffStatus = $this->actingAs($staff)->call($method, $url)->getStatusCode();
            $adminOnly ? $this->assertSame(403, $staffStatus, "Nhân viên vào được route chỉ dành cho quản trị: {$r->getName()}")
                : $this->assertNotSame(403, $staffStatus, "Nhân viên bị chặn nhầm ở {$r->getName()}");
            $this->assertNotSame(403, $this->actingAs($this->admin())->call($method, $url)->getStatusCode(), "Quản trị bị chặn ở {$r->getName()}");
            $checked++;
        }
        $this->assertGreaterThan(50, $checked);
    }

    /**
     * Phân quyền nhân viên (đợt 2): route nào gắn 'admin:<quyền>' thì nhân viên THIẾU quyền đó nhận 403
     * (trước khi nạp model: không dò được id), nhân viên CHỈ có đúng quyền đó thì vào được. Tự đọc router.
     */
    public function test_staff_permission_groups_guard_their_routes(): void
    {
        $fx = $this->fixtures();
        $perms = StaffPermission::values();
        $checked = 0;
        foreach ($this->adminRoutes() as $r) {
            $needed = collect($r->gatherMiddleware())->map(fn ($m) => is_string($m) && str_starts_with($m, 'admin:') ? substr($m, 6) : null)
                ->filter(fn ($p) => $p !== null && $p !== 'admin')->values();
            if ($needed->isEmpty() || in_array('admin:admin', $r->gatherMiddleware(), true)) {
                continue;
            }
            $this->assertCount(1, $needed, "Route {$r->getName()} gắn nhiều nhóm quyền");
            $perm = $needed->first();
            $this->assertContains($perm, $perms, "Route {$r->getName()} dùng quyền lạ: {$perm}");
            $method = $r->methods()[0];
            $url = $this->url($r, $fx);

            $without = User::factory()->staff()->create(['permissions' => array_values(array_diff($perms, [$perm]))]);
            $this->actingAs($without)->call($method, $url)->assertForbidden();
            $only = User::factory()->staff()->create(['permissions' => [$perm]]);
            $this->assertNotSame(403, $this->actingAs($only)->call($method, $url)->getStatusCode(), "Nhân viên có quyền {$perm} bị chặn ở {$r->getName()}");
            $checked++;
        }
        $this->assertGreaterThan(40, $checked);
    }

    /** F2: không lộ id nào tồn tại qua 404 / 403. */
    public function test_unauthorized_users_get_403_for_missing_and_existing_ids_alike(): void
    {
        $this->actingAs($this->customer());
        foreach (['admin.orders.show' => 'order', 'admin.goods-receipts.show' => 'goods_receipt', 'admin.customers.show' => 'user'] as $name => $param) {
            $this->get(route($name, [$param => 987654]))->assertForbidden();
        }
    }

    public function test_money_and_policy_routes_are_admin_only(): void
    {
        $adminOnly = collect($this->adminRoutes())->filter(fn ($r) => in_array('admin:admin', $r->gatherMiddleware(), true))->map->getName()->all();
        foreach (['admin.orders.confirm-transfer', 'admin.orders.refunds.store', 'admin.payments.confirm-refund', 'admin.purchase-requests.approve',
            'admin.purchase-requests.reject', 'admin.coupons.store', 'admin.tiers.update', 'admin.customers.tier',
            'admin.staff.index', 'admin.staff.store', 'admin.staff.update', 'admin.stock-counts.store', 'admin.stock-counts.update'] as $name) {
            $this->assertContains($name, $adminOnly, "{$name} phải chỉ dành cho quản trị");
        }
    }

    public function test_customer_resources_are_owner_only(): void
    {
        $fx = $this->fixtures();
        $order = Order::findOrFail($fx['order']);
        $other = User::factory()->create();
        $cases = [
            ['GET', route('orders.show', $order)], ['POST', route('orders.pay', $order)],
            ['POST', route('orders.cancel', $order), ['reason' => 'x']], ['POST', route('orders.return', $order), ['reason' => 'x']],
            ['POST', route('orders.note', $order), ['note' => 'ghi đè']], ['GET', route('orders.invoice', $order)],
            ['PUT', route('addresses.update', $fx['address']), $this->shipping()],
            ['POST', route('addresses.default', $fx['address'])], ['DELETE', route('addresses.destroy', $fx['address'])],
            ['DELETE', route('comments.destroy', $fx['comment'])],
        ];
        foreach ($cases as $c) {
            $status = $this->actingAs($other)->call($c[0], $c[1], $c[2] ?? [])->getStatusCode();
            $this->assertContains($status, [403, 404], "Người khác truy cập được {$c[0]} {$c[1]} (HTTP {$status})");
        }
        $this->assertSame(1, $this->customer()->addresses()->count(), 'Địa chỉ của chủ bị người khác xóa');
        $this->assertNotNull(Comment::find($fx['comment']));
        $this->assertNull($order->fresh()->note, 'Người khác sửa được ghi chú đơn');
    }
}

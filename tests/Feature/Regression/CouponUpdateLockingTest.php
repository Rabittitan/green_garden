<?php

namespace Tests\Feature\Regression;

use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * Nhóm B: admin sửa mã giảm giá. Trước đây việc này chạy trong controller:
 *  - "mã đã có người dùng?" xét 2 lần, ngoài khóa, trên model nạp trước giao dịch -> có thể đổi luật mà không đổi phạm vi;
 *  - used_count cũ -> hạ usage_limit xuống dưới số lượt thật gây vi phạm CHECK (lỗi 500);
 *  - khóa coupons trước plants -> deadlock với checkout (xem ConcurrencyTest), giao dịch không thử lại.
 */
class CouponUpdateLockingTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private function coupon(): Coupon
    {
        $c = Coupon::create(['code' => 'CAY10', 'name' => 'Giảm 10% cây', 'type' => 'percent', 'value' => 10,
            'scope' => 'plants', 'usage_limit' => 5, 'usage_limit_per_user' => 3]);
        $c->plants()->sync([1, 3]);

        return $c;
    }

    public function test_stale_model_cannot_change_rules_of_a_coupon_used_meanwhile(): void
    {
        $stale = $this->coupon();                                   // admin mở form khi mã CHƯA ai dùng
        $this->place(cart: [1 => 1], coupon: 'CAY10');              // trong lúc đó khách dùng mã
        app(CouponService::class)->update($stale, ['name' => 'Đổi tên', 'value' => 50, 'scope' => 'order', 'usage_limit' => 9], [], []);

        $c = $stale->fresh();
        $this->assertSame(['Đổi tên', 10, 'plants', 9], [$c->name, $c->value, $c->scope->value, $c->usage_limit]);   // luật giữ nguyên
        $this->assertSame([1, 3], $c->plants()->pluck('plants.id')->sort()->values()->all());                       // phạm vi giữ nguyên
    }

    public function test_usage_limit_below_real_usage_is_a_business_error_not_a_500(): void
    {
        $stale = $this->coupon();
        $this->place(cart: [1 => 1], coupon: 'CAY10');
        $this->place(cart: [3 => 1], coupon: 'CAY10');              // đã dùng 2 lượt; model cũ vẫn tưởng 0

        try {
            app(CouponService::class)->update($stale, ['usage_limit' => 1], [], []);
            $this->fail('Phải chặn bằng lỗi nghiệp vụ');
        } catch (BusinessException $e) {
            $this->assertSame('Tổng lượt không được nhỏ hơn số lượt đã dùng (2).', $e->getMessage());
        }
        $this->assertSame(5, $stale->fresh()->usage_limit);
    }

    public function test_unused_coupon_changes_rules_and_scope_together(): void
    {
        $c = $this->coupon();
        app(CouponService::class)->update($c, ['value' => 15, 'scope' => 'plants'], [5, 7], []);
        $c->refresh();
        $this->assertSame(15, $c->value);
        $this->assertSame([5, 7], $c->plants()->pluck('plants.id')->sort()->values()->all());
    }

    public function test_admin_form_still_works_over_http(): void
    {
        $c = $this->coupon();
        $payload = ['code' => 'CAY10', 'name' => 'Giảm 12%', 'type' => 'percent', 'value' => 12, 'min_order_amount' => 0,
            'scope' => 'plants', 'plant_ids' => [3, 5], 'usage_limit' => 5, 'usage_limit_per_user' => 3, 'is_active' => 1];
        $this->actingAs($this->admin())->put(route('admin.coupons.update', $c), $payload)
            ->assertRedirect(route('admin.coupons.index'))->assertSessionHas('success');
        $this->assertSame([12, [3, 5]], [$c->fresh()->value, $c->plants()->pluck('plants.id')->sort()->values()->all()]);
    }
}

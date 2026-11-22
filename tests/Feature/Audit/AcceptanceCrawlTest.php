<?php

namespace Tests\Feature\Audit;

use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\CommentService;
use App\Services\PurchaseService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * H8 — nghiệm thu C5 + C9 bằng cách "bò" qua MỌI route GET với 4 vai trò:
 *  - C9: không trang nào trả 500 (mọi lỗi phải là trang/thông báo tiếng Việt có kiểm soát)
 *  - C5: không route GET nào ghi DB (GET bị prefetch, bị site khác nhúng ảnh/link -> không được đổi dữ liệu)
 * Thêm route GET mới mà test này không biết dựng tham số -> test đỏ, buộc phải xem xét.
 */
class AcceptanceCrawlTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    /**
     * GET được phép ghi, có lý do:
     *  - verification.verify: link ký số gửi qua email (chuẩn Laravel), chỉ ghi email_verified_at của chính người đó
     *  - payment.vnpay.ipn: VNPay chỉ gọi bằng GET; có chữ ký HMAC + idempotent
     * Ngoại lệ có điều kiện (không nằm trong danh sách vì fixture không quá hạn): orders.show / orders.index
     * tự hủy đơn ĐÃ quá hạn thanh toán khi khách mở — đúng việc lệnh cron chạy mỗi phút, idempotent,
     * không ai kích hoạt được điều gì ngoài kết cục đã định (test_expired_order_is_cancelled_when_customer_opens_it).
     */
    private const GET_MAY_WRITE = ['verification.verify', 'payment.vnpay.ipn'];

    /** Cần query string riêng của cổng thanh toán / chữ ký -> đã có test chuyên biệt (CustomerFlowTest, PaymentTest). */
    private const NEEDS_GATEWAY_QUERY = ['payment.vnpay.ipn', 'payment.vnpay.return', 'payment.fake.show', 'verification.verify'];

    /** @return array<string,mixed> */
    private function fixtures(): array
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $staff = User::factory()->staff()->create();
        $pr = app(PurchaseService::class)->createRequest($staff, [1 => ['quantity' => 3, 'estimated_unit_cost' => 1]]);
        $receipt = app(PurchaseService::class)->createReceipt($staff, Supplier::first(), null, [1 => ['quantity_received' => 1, 'unit_cost' => 1]]);
        app(CommentService::class)->post($this->customer(), Plant::find(1), 'Cây này tưới mấy lần một tuần?');
        (new PlantReview(['plant_id' => 1, 'user_id' => $this->customer()->id, 'order_item_id' => $order->items()->sole()->id, 'rating' => 5]))->save();

        return [
            'order' => $order, 'plant' => 1, 'id' => 1, 'coupon' => Coupon::first()->id, 'user' => $this->customer()->id,
            'purchase_request' => $pr->id, 'supplier' => Supplier::first()->id, 'goods_receipt' => $receipt->id,
            'address' => UserAddress::first()->id,
            'token' => 'token-dat-lai-mat-khau-khong-hop-le',               // trang đặt lại mật khẩu: chỉ hiện form, không ghi DB
        ];
    }

    /** @return list<RouteDef> */
    private function getRoutes(): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), fn (RouteDef $r) => in_array('GET', $r->methods(), true)
            && $r->getName() !== null && ! str_starts_with($r->getName(), 'storage.') && ! in_array($r->getName(), self::NEEDS_GATEWAY_QUERY, true)
            && ! str_starts_with($r->uri(), '_') && $r->uri() !== 'up'));
    }

    public function test_every_get_page_for_every_role_never_500s_and_never_writes(): void
    {
        $fx = $this->fixtures();
        $roles = ['khách vãng lai' => null, 'khách' => $this->customer(), 'nhân viên' => User::factory()->staff()->create(), 'quản trị' => $this->admin()];
        $writes = [];
        $current = '';
        DB::listen(function (QueryExecuted $q) use (&$writes, &$current) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $q->sql)) {
                $writes[$current][] = $q->sql;
            }
        });

        $visited = 0;
        foreach ($this->getRoutes() as $r) {
            $params = [];
            foreach ($r->parameterNames() as $p) {
                $params[$p] = $fx[$p] ?? $this->fail("Route GET mới {$r->getName()} có tham số {$p} chưa được nghiệm thu: thêm fixture và xem nó có ghi DB không");
            }
            $url = route($r->getName(), $params);

            foreach ($roles as $label => $user) {
                $user ? $this->actingAs($user) : auth()->logout();
                $current = "{$r->getName()} ({$label})";
                $status = $this->get($url)->getStatusCode();
                $this->assertLessThan(500, $status, "Lỗi {$status} ở {$current}");
                $visited++;
            }
        }

        $this->assertSame([], $writes, 'Route GET ghi DB (vi phạm C5): '.json_encode(array_keys($writes), JSON_UNESCAPED_UNICODE));
        $this->assertGreaterThan(150, $visited);
    }

    public function test_only_reviewed_get_routes_may_write(): void
    {
        // Danh sách ngoại lệ phải còn đúng: route bị xóa/đổi tên thì dọn danh sách, không để sót "giấy phép" cũ
        foreach (self::GET_MAY_WRITE as $name) {
            $this->assertTrue(Route::has($name), "Ngoại lệ {$name} không còn tồn tại");
            $this->assertContains('GET', Route::getRoutes()->getByName($name)->methods());
        }
    }
}

<?php

namespace Tests\Feature\Http;

use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * Tiêu chí C6: mỗi trang chính tối đa 15 câu SQL, và số câu KHÔNG tăng theo số dòng dữ liệu (chống N+1).
 */
class QueryBudgetTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private const BUDGET = 15;

    private function countQueries(callable $fn): int
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $fn();

        return $n;
    }

    /** @return array<string, array{string, string}> trang => [vai trò, route] */
    public static function pages(): array
    {
        return [
            'trang chủ' => ['guest', 'home'],
            'danh sách cây' => ['guest', 'plants.index'],
            'chi tiết cây (đánh giá + hỏi đáp)' => ['customer', 'plants.show'],
            'bài viết (bình luận)' => ['guest', 'news.show'],
            'giỏ hàng' => ['customer', 'cart.index'],
            'đặt hàng' => ['customer', 'checkout.form'],
            'đơn của tôi' => ['customer', 'orders.index'],
            'chi tiết đơn (khách)' => ['customer', 'orders.show'],
            'dashboard' => ['admin', 'admin.dashboard'],
            'danh sách đơn' => ['admin', 'admin.orders.index'],
            'chi tiết đơn (admin)' => ['admin', 'admin.orders.show'],
            'kho' => ['admin', 'admin.inventory.index'],
            'sổ kho' => ['admin', 'admin.inventory.show'],
            'khách hàng' => ['admin', 'admin.customers.index'],
            'mã giảm giá' => ['admin', 'admin.coupons.index'],
            'yêu cầu nhập' => ['admin', 'admin.purchase-requests.index'],
            'phiếu nhập kho' => ['admin', 'admin.goods-receipts.index'],
            'nhà cung cấp' => ['admin', 'admin.suppliers.index'],
            'kiểm duyệt đánh giá' => ['admin', 'admin.reviews.index'],
            'hỏi đáp' => ['admin', 'admin.comments.index'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_stays_within_query_budget_and_does_not_grow_with_data(string $role, string $route): void
    {
        $measure = function () use ($role, $route) {
            $order = Order::latest('id')->first();
            $params = match ($route) {
                'orders.show', 'admin.orders.show' => [$order],
                'admin.inventory.show', 'plants.show', 'news.show' => [1],
                default => [],
            };
            $user = match ($role) {
                'customer' => $this->customer(),
                'admin' => $this->admin(),
                default => null,
            };
            $user ? $this->actingAs($user) : auth()->logout();
            if ($route === 'checkout.form') {
                session(['cart' => [1 => 1, 3 => 2, 5 => 1]]);
            }

            return $this->countQueries(fn () => $this->get(route($route, $params))->assertOk());
        };

        $this->place(cart: [1 => 1, 3 => 1], method: PaymentMethod::Vnpay);
        $this->addContent();                                                  // có sẵn 1 lượt dữ liệu: đo đúng N+1, không đo "có/không có dữ liệu"
        $small = $measure();

        foreach (User::factory()->count(5)->create() as $u) {               // thêm dữ liệu: số câu không được tăng
            $this->place($u, [1 => 1, 3 => 1, 5 => 1]);
        }
        $this->place(cart: [1 => 1, 3 => 1, 5 => 2, 12 => 1], method: PaymentMethod::Vnpay);
        $this->addContent();
        $large = $measure();

        $this->assertLessThanOrEqual(self::BUDGET, $large, "Trang {$route}: {$large} câu SQL (giới hạn ".self::BUDGET.')');
        $this->assertLessThanOrEqual($small + 2, $large, "Trang {$route}: số câu tăng theo dữ liệu ({$small} -> {$large}), nghi N+1");
    }

    /** Thêm hỏi đáp (có shop trả lời), bình luận bài viết, đánh giá đã duyệt cho mọi khách chưa có. */
    private function addContent(): void
    {
        $comments = app(CommentService::class);
        foreach (User::where('role', 'customer')->whereDoesntHave('comments')->get() as $u) {
            $q = $comments->post($u, Plant::find(1), 'Câu hỏi của '.$u->name);
            $comments->post($this->admin(), Plant::find(1), 'Shop trả lời', $q);
            $comments->post($u, Article::find(1), 'Bình luận của '.$u->name);
            $r = new PlantReview(['plant_id' => 1, 'user_id' => $u->id, 'order_item_id' => OrderItem::first()->id, 'rating' => 5]);
            $r->status = ReviewStatus::Approved;
            $r->save();
        }
    }
}

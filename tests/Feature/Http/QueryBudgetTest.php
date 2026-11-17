<?php

namespace Tests\Feature\Http;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
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
        ];
    }

    #[DataProvider('pages')]
    public function test_page_stays_within_query_budget_and_does_not_grow_with_data(string $role, string $route): void
    {
        $measure = function () use ($role, $route) {
            $order = Order::latest('id')->first();
            $params = match ($route) {
                'orders.show', 'admin.orders.show' => [$order],
                'admin.inventory.show' => [1],
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
        $small = $measure();

        foreach (User::factory()->count(5)->create() as $u) {               // thêm dữ liệu: số câu không được tăng
            $this->place($u, [1 => 1, 3 => 1, 5 => 1]);
        }
        $this->place(cart: [1 => 1, 3 => 1, 5 => 2, 12 => 1], method: PaymentMethod::Vnpay);
        $large = $measure();

        $this->assertLessThanOrEqual(self::BUDGET, $large, "Trang {$route}: {$large} câu SQL (giới hạn ".self::BUDGET.')');
        $this->assertLessThanOrEqual($small + 2, $large, "Trang {$route}: số câu tăng theo dữ liệu ({$small} -> {$large}), nghi N+1");
    }
}

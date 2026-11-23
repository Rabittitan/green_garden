<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\OrderItem;
use App\Models\Plant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    // Hiển thị trang chủ
    public function index(): View
    {
        // Lấy tất cả danh mục kèm số cây của từng danh mục (withCount tạo ra biến plants_count)
        $categories = Category::active()->withCount(['plants' => fn ($q) => $q->active()])->orderBy('sort_order')->get();

        // Lấy 16 cây mới nhất (theo id giảm dần), kèm danh mục để tránh truy vấn lặp
        $newPlants = Plant::with('category')->active()->latest('id')->take(16)->get();

        $articles = Article::published()->latest('published_at')->take(3)->get();

        // Slide đầu trang (admin quản lý); chưa bật slide nào thì view hiện banner mặc định
        $slides = HomeSlide::shown()->take(HomeSlide::MAX)->get();

        [$hotPlants, $hotSold] = $this->hotPlants();

        // Gửi dữ liệu sang view resources/views/home.blade.php
        return view('home', compact('categories', 'newPlants', 'articles', 'slides', 'hotPlants', 'hotSold'));
    }

    /**
     * "Sản phẩm hot": cây bán được nhiều nhất trong shop.home.hot_days ngày qua, tính trên đơn đã đặt và
     * chưa hủy / hoàn (chờ xác nhận, đã xác nhận, đang giao, hoàn thành). Đơn chờ thanh toán chưa tính.
     * Thiếu thì bù bằng cây được đánh giá cao rồi cây mới, để mục luôn đủ hàng. 2–3 câu SQL, không phụ thuộc số đơn.
     *
     * @return array{0: Collection<int, Plant>, 1: array<int, int>} [cây, plant_id => số đã bán trong kỳ]
     */
    private function hotPlants(): array
    {
        $limit = (int) config('shop.home.hot_limit');
        $sold = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('plants', 'plants.id', '=', 'order_items.plant_id')
            ->whereIn('orders.status', [OrderStatus::Pending->value, OrderStatus::Confirmed->value, OrderStatus::Shipping->value, OrderStatus::Completed->value])
            ->where('orders.placed_at', '>=', now()->subDays((int) config('shop.home.hot_days')))
            ->where('plants.is_active', true)->whereNull('plants.deleted_at')
            ->groupBy('order_items.plant_id')
            ->selectRaw('order_items.plant_id AS pid, SUM(order_items.quantity) AS sold')
            ->orderByDesc('sold')->orderBy('pid')
            ->limit($limit)
            ->toBase()->get()
            ->mapWithKeys(fn ($r) => [(int) $r->pid => (int) $r->sold])->all();

        $plants = Plant::with('category')->active()->whereIn('id', array_keys($sold))->get()
            ->sortBy(fn (Plant $p) => array_search($p->id, array_keys($sold), true))->values();
        if ($plants->count() < $limit) {
            $plants = $plants->concat(Plant::with('category')->active()->whereNotIn('id', array_keys($sold))
                ->orderByDesc('rating_count')->orderByDesc('id')->take($limit - $plants->count())->get());
        }

        return [$plants, $sold];
    }
}

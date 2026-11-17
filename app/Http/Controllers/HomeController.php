<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Plant;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    // Hiển thị trang chủ
    public function index(): View
    {
        // Lấy tất cả danh mục kèm số cây của từng danh mục (withCount tạo ra biến plants_count)
        $categories = Category::active()->withCount(['plants' => fn ($q) => $q->active()])->orderBy('sort_order')->get();

        // Lấy 8 cây mới nhất (latest = sắp xếp created_at giảm dần), kèm danh mục để tránh truy vấn lặp
        $newPlants = Plant::with('category')->active()->latest('id')->take(16)->get();

        $articles = Article::published()->latest('published_at')->take(3)->get();

        // Gửi dữ liệu sang view resources/views/home.blade.php
        return view('home', compact('categories', 'newPlants', 'articles'));
    }
}

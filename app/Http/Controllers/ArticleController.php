<?php

namespace App\Http\Controllers;

use App\Enums\CommentStatus;
use App\Models\Article;
use Illuminate\Contracts\View\View;

class ArticleController extends Controller
{
    // Trang danh sách tin tức
    public function index(): View
    {
        // Lấy bài mới nhất trước, 6 bài mỗi trang
        $articles = Article::published()->latest('published_at')->paginate(6);

        // Gửi sang view resources/views/news/index.blade.php
        return view('news.index', compact('articles'));
    }

    // Trang chi tiết 1 bài
    public function show(int $id): View
    {
        // Tìm bài theo id, không thấy thì tự trả về trang 404
        $article = Article::published()->with(['author', 'plants'])->findOrFail($id);

        // Lấy 3 bài khác (trừ bài đang xem) để gợi ý đọc tiếp
        $others = Article::published()->where('id', '!=', $article->id)->latest('published_at')->take(3)->get();

        $comments = $article->comments()->whereNull('parent_id')->where('status', CommentStatus::Published->value)
            ->with(['user', 'replies' => fn ($q) => $q->where('status', CommentStatus::Published->value)->with('user')])
            ->orderByDesc('id')->simplePaginate(10)->withQueryString();

        return view('news.show', compact('article', 'others', 'comments'));
    }
}

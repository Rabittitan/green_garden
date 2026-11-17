<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
   // Trang danh sách tin tức
    public function index()
    {
        // Lấy bài mới nhất trước, 6 bài mỗi trang
        $articles = Article::latest()->paginate(6);

        // Gửi sang view resources/views/news/index.blade.php
        return view('news.index', compact('articles'));
    }

    // Trang chi tiết 1 bài
    public function show($id)
    {
        // Tìm bài theo id, không thấy thì tự trả về trang 404
        $article = Article::findOrFail($id);

        // Lấy 3 bài khác (trừ bài đang xem) để gợi ý đọc tiếp
        $others = Article::where('id', '!=', $article->id)->latest()->take(3)->get();

        return view('news.show', compact('article', 'others'));
    }
}

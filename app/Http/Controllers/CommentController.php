<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Plant;
use App\Services\CommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Hỏi đáp dưới cây / bình luận dưới bài viết. */
class CommentController extends Controller
{
    public function __construct(private CommentService $comments) {}

    public function storeForPlant(Request $request, int $id): RedirectResponse
    {
        return $this->store($request, Plant::active()->findOrFail($id));
    }

    public function storeForArticle(Request $request, int $id): RedirectResponse
    {
        return $this->store($request, Article::published()->findOrFail($id));
    }

    private function store(Request $request, Plant|Article $target): RedirectResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:2000'], 'parent_id' => ['nullable', 'integer']], [], ['content' => 'nội dung']);
        $parent = isset($data['parent_id']) ? Comment::findOrFail($data['parent_id']) : null;
        try {
            $this->comments->post($request->user(), $target, $data['content'], $parent, $request->ip());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', $parent ? 'Đã gửi trả lời.' : 'Đã gửi câu hỏi / bình luận.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        try {
            $this->comments->delete($comment, $request->user());
        } catch (BusinessException $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('success', 'Đã xóa bình luận.');
    }
}

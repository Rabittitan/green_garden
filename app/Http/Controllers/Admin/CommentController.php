<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommentStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Hỏi đáp: xem câu hỏi chưa được shop trả lời, trả lời (ghim), ẩn / hiện. */
class CommentController extends Controller
{
    public function __construct(private CommentService $comments) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['filter' => ['nullable', Rule::in(['unanswered', 'hidden', 'all'])]])['filter'] ?? 'unanswered';
        $comments = Comment::with(['user', 'commentable', 'replies.user'])->whereNull('parent_id')
            ->when($filter === 'unanswered', fn ($q) => $q->where('status', CommentStatus::Published->value)
                ->whereDoesntHave('replies', fn ($r) => $r->where('is_pinned', true)))
            ->when($filter === 'hidden', fn ($q) => $q->where('status', CommentStatus::Hidden->value))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.comments.index', compact('comments', 'filter'));
    }

    public function reply(Request $request, Comment $comment)
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']], [], ['content' => 'nội dung']);
        try {
            $this->comments->post($request->user(), $comment->commentable, $data['content'], $comment, $request->ip());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã trả lời.');
    }

    public function toggle(Comment $comment)
    {
        $this->comments->setStatus($comment, $comment->status === CommentStatus::Hidden ? CommentStatus::Published : CommentStatus::Hidden);

        return back()->with('success', 'Đã cập nhật trạng thái bình luận.');
    }
}

<?php

namespace App\Services;

use App\Enums\CommentStatus;
use App\Exceptions\BusinessException;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Hỏi đáp / bình luận (không có sao, không ảnh hưởng điểm): dùng chung cho cây và bài viết.
 *  - Ai đăng nhập cũng hỏi được; trả lời lồng đúng 1 cấp (trả lời của trả lời được gắn về câu hỏi gốc).
 *  - Nhân viên trả lời được ghim; nhân viên ẩn / hiện được mọi bình luận.
 */
class CommentService
{
    public function post(User $user, Model $commentable, string $content, ?Comment $parent = null, ?string $ip = null): Comment
    {
        $content = trim($content);
        if (mb_strlen($content) < 2) {
            throw new BusinessException('Nội dung quá ngắn.');
        }
        if ($parent) {
            if ($parent->commentable_type !== $commentable->getMorphClass() || (int) $parent->commentable_id !== (int) $commentable->getKey()) {
                throw new BusinessException('Câu trả lời không thuộc chủ đề này.');
            }
            if ($parent->status !== CommentStatus::Published) {
                throw new BusinessException('Không thể trả lời bình luận đã bị ẩn.');
            }
            $parent = $parent->parent_id ? $parent->parent : $parent;          // chỉ lồng 1 cấp
        }

        $comment = new Comment(['user_id' => $user->id, 'parent_id' => $parent?->id, 'content' => $content, 'ip_address' => $ip]);
        $comment->commentable()->associate($commentable);
        $comment->status = CommentStatus::Published;
        $comment->is_pinned = $parent !== null && $user->isBackOffice();      // shop trả lời -> ghim
        $comment->save();

        return $comment;
    }

    public function setStatus(Comment $comment, CommentStatus $status): void
    {
        $comment->forceFill(['status' => $status])->save();
    }

    /** Chủ bình luận hoặc nhân viên xóa (xóa mềm; xóa câu hỏi thì các trả lời cũng ẩn theo). */
    public function delete(Comment $comment, User $by): void
    {
        if ($comment->user_id !== $by->id && ! $by->isBackOffice()) {
            throw new BusinessException('Bạn không thể xóa bình luận của người khác.');
        }
        $comment->replies()->each(fn (Comment $r) => $r->delete());
        $comment->delete();
    }
}

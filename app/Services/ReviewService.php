<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\BusinessException;
use App\Models\OrderItem;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Đánh giá cây. Bất biến (đối soát trong shop:reconcile):
 *   plants.rating_sum   = SUM(rating)  của các review approved (chưa xóa)
 *   plants.rating_count = COUNT(*)     của các review approved (chưa xóa)
 *   plant_reviews.helpful_count = số dòng review_votes của review đó
 *
 * Quy tắc:
 *  - Chỉ khách đã NHẬN cây (dòng hàng thuộc đơn completed của chính họ) mới được đánh giá.
 *  - Mỗi khách 1 đánh giá / 1 cây; sửa nội dung -> phải duyệt lại.
 *  - Thứ tự khóa: users -> plant_reviews -> plants (giống các luồng khác: users đứng đầu).
 */
class ReviewService
{
    /** Dòng hàng làm bằng chứng mua (đơn đã hoàn thành gần nhất), null nếu chưa đủ điều kiện. */
    public function proofOfPurchase(User $user, int $plantId): ?OrderItem
    {
        return OrderItem::where('plant_id', $plantId)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', OrderStatus::Completed->value))
            ->latest('id')->first();
    }

    public function submit(User $user, int $plantId, int $rating, ?string $title, ?string $content): PlantReview
    {
        if ($rating < 1 || $rating > 5) {
            throw new BusinessException('Số sao phải từ 1 đến 5.');
        }

        return DB::transaction(function () use ($user, $plantId, $rating, $title, $content) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();      // tuần tự hóa: bấm gửi 2 lần không lỗi UNIQUE
            $orderItem = $this->proofOfPurchase($user, $plantId)
                ?? throw new BusinessException('Bạn cần mua và nhận cây này trước khi đánh giá.');

            $review = PlantReview::withTrashed()->where('user_id', $user->id)->where('plant_id', $plantId)->lockForUpdate()->first()
                ?? new PlantReview(['user_id' => $user->id, 'plant_id' => $plantId]);

            // Sửa một đánh giá đang được tính điểm: gỡ điểm cũ khỏi tổng trước
            if ($review->exists && ! $review->trashed() && $review->status === ReviewStatus::Approved) {
                $this->applyToPlant($plantId, -$review->rating, -1);
            }

            $review->fill(['order_item_id' => $orderItem->id, 'rating' => $rating, 'title' => $title, 'content' => $content]);
            $review->status = ReviewStatus::Pending;                       // sửa nội dung thì phải duyệt lại
            $review->reject_reason = null;
            $review->deleted_at = null;
            $review->save();

            return $review;
        }, 3);
    }

    public function moderate(PlantReview $review, ReviewStatus $to, User $admin, ?string $reason = null): void
    {
        if ($to === ReviewStatus::Rejected && trim((string) $reason) === '') {
            throw new BusinessException('Phải ghi lý do từ chối.');
        }
        DB::transaction(function () use ($review, $to, $admin, $reason) {
            $review = PlantReview::lockForUpdate()->findOrFail($review->id);
            $from = $review->status;
            if ($from === $to) {
                return;
            }
            if ($from === ReviewStatus::Approved) {
                $this->applyToPlant($review->plant_id, -$review->rating, -1);
            }
            if ($to === ReviewStatus::Approved) {
                $this->applyToPlant($review->plant_id, $review->rating, 1);
            }
            $review->status = $to;
            $review->reject_reason = $to === ReviewStatus::Rejected ? $reason : null;
            $review->moderated_by = $admin->id;
            $review->moderated_at = now();
            $review->save();
        }, 3);
    }

    /** Shop trả lời công khai dưới đánh giá (rỗng = gỡ trả lời). */
    public function reply(PlantReview $review, ?string $text): void
    {
        $text = trim((string) $text);
        $review->forceFill(['shop_reply' => $text ?: null, 'shop_replied_at' => $text ? now() : null])->save();
    }

    /** Khách tự xóa đánh giá của mình, hoặc admin xóa. */
    public function delete(PlantReview $review): void
    {
        DB::transaction(function () use ($review) {
            $review = PlantReview::lockForUpdate()->findOrFail($review->id);
            if ($review->status === ReviewStatus::Approved) {
                $this->applyToPlant($review->plant_id, -$review->rating, -1);
            }
            $review->delete();
        }, 3);
    }

    /** Bấm "Hữu ích" lần nữa để bỏ. Không tự bình chọn cho đánh giá của mình; chỉ đánh giá đã duyệt. Trả true nếu đang bình chọn. */
    public function toggleHelpful(PlantReview $review, User $user): bool
    {
        return DB::transaction(function () use ($review, $user) {
            $review = PlantReview::lockForUpdate()->findOrFail($review->id);
            if ($review->status !== ReviewStatus::Approved) {
                throw new BusinessException('Đánh giá chưa được duyệt.');
            }
            if ($review->user_id === $user->id) {
                throw new BusinessException('Không thể bình chọn cho đánh giá của chính bạn.');
            }
            $deleted = DB::table('review_votes')->where('plant_review_id', $review->id)->where('user_id', $user->id)->delete();
            if (! $deleted) {
                DB::table('review_votes')->insert(['plant_review_id' => $review->id, 'user_id' => $user->id, 'created_at' => now()]);
            }
            $review->forceFill(['helpful_count' => DB::table('review_votes')->where('plant_review_id', $review->id)->count()])->save();

            return ! $deleted;
        }, 3);
    }

    private function applyToPlant(int $plantId, int $sumDelta, int $countDelta): void
    {
        Plant::withTrashed()->whereKey($plantId)->update([
            'rating_sum' => DB::raw("rating_sum + ({$sumDelta})"),
            'rating_count' => DB::raw("rating_count + ({$countDelta})"),
        ]);
    }

    /** Dựng lại số liệu từ đầu: dùng khi đối soát lệch hoặc sau khi sửa dữ liệu bằng tay. */
    public function rebuild(): void
    {
        DB::transaction(function () {
            Plant::withTrashed()->update(['rating_sum' => 0, 'rating_count' => 0]);
            PlantReview::where('status', ReviewStatus::Approved->value)
                ->selectRaw('plant_id, SUM(rating) s, COUNT(*) c')->groupBy('plant_id')
                ->get()->each(fn ($r) => Plant::withTrashed()->whereKey($r->plant_id)
                ->update(['rating_sum' => $r->s, 'rating_count' => $r->c]));
        });
    }
}

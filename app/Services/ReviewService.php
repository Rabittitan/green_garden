<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\OrderItem;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Đánh giá cây. Giữ bất biến:
 *   plants.rating_sum   = SUM(rating)  của các review status = approved
 *   plants.rating_count = COUNT(*)     của các review status = approved
 */
class ReviewService
{
    /** Khách viết đánh giá. Chỉ cho phép khi có dòng hàng của cây này trong đơn ĐÃ HOÀN THÀNH. */
    public function submit(User $user, int $plantId, int $rating, ?string $title, ?string $content): PlantReview
    {
        $orderItem = OrderItem::where('plant_id', $plantId)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', 'completed'))
            ->latest('id')->first();

        if (! $orderItem) {
            throw new RuntimeException('Bạn cần mua và nhận cây này trước khi đánh giá.');
        }

        return DB::transaction(function () use ($user, $plantId, $rating, $title, $content, $orderItem) {
            $review = PlantReview::withTrashed()->lockForUpdate()
                ->firstOrNew(['user_id' => $user->id, 'plant_id' => $plantId]);

            // Nếu đang sửa một review đã duyệt: gỡ điểm cũ khỏi tổng trước
            if ($review->exists && ! $review->trashed() && $review->status === ReviewStatus::Approved) {
                $this->applyToPlant($plantId, -$review->rating, -1);
            }

            $review->fill(['order_item_id' => $orderItem->id, 'rating' => $rating, 'title' => $title, 'content' => $content]);
            $review->status = ReviewStatus::Pending;     // sửa nội dung thì phải duyệt lại
            $review->deleted_at = null;
            $review->save();

            return $review;
        });
    }

    public function moderate(PlantReview $review, ReviewStatus $to, User $admin, ?string $reason = null): void
    {
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
        });
    }

    public function delete(PlantReview $review): void
    {
        DB::transaction(function () use ($review) {
            $review = PlantReview::lockForUpdate()->findOrFail($review->id);
            if ($review->status === ReviewStatus::Approved) {
                $this->applyToPlant($review->plant_id, -$review->rating, -1);
            }
            $review->delete();
        });
    }

    private function applyToPlant(int $plantId, int $sumDelta, int $countDelta): void
    {
        Plant::withTrashed()->whereKey($plantId)->update([
            'rating_sum' => DB::raw("rating_sum + ({$sumDelta})"),
            'rating_count' => DB::raw("rating_count + ({$countDelta})"),
        ]);
    }

    /** Dựng lại số liệu từ đầu: dùng khi đối soát hoặc sau khi sửa dữ liệu bằng tay. */
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

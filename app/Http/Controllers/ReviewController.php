<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Khách viết / sửa / xóa đánh giá và bình chọn "Hữu ích". */
class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function store(Request $request, int $id): RedirectResponse
    {
        $plant = Plant::withTrashed()->findOrFail($id);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'content' => ['nullable', 'string', 'max:2000'],
        ], [], ['rating' => 'số sao']);
        try {
            $this->reviews->submit($request->user(), $plant->id, (int) $data['rating'], $data['title'] ?? null, $data['content'] ?? null);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cảm ơn bạn! Đánh giá sẽ hiển thị sau khi shop duyệt.');
    }

    public function destroy(Request $request, PlantReview $review): RedirectResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);
        $this->reviews->delete($review);

        return back()->with('success', 'Đã xóa đánh giá.');
    }

    public function vote(Request $request, PlantReview $review): RedirectResponse
    {
        try {
            $on = $this->reviews->toggleHelpful($review, $request->user());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $on ? 'Cảm ơn bạn đã bình chọn.' : 'Đã bỏ bình chọn.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\PlantReview;
use App\Services\ReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kiểm duyệt đánh giá + trả lời của shop. */
class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(ReviewStatus::class)]])['status'] ?? 'pending';
        $reviews = PlantReview::with(['plant', 'user', 'orderItem.order'])->where('status', $status)->latest('id')->paginate(20)->withQueryString();
        $counts = PlantReview::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return view('admin.reviews.index', compact('reviews', 'counts', 'status'));
    }

    public function moderate(Request $request, PlantReview $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'reason' => ['nullable', 'string', 'max:255']]);
        try {
            $this->reviews->moderate($review, ReviewStatus::from($data['status']), $request->user(), $data['reason'] ?? null);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['status'] === 'approved' ? 'Đã duyệt đánh giá.' : 'Đã từ chối đánh giá.');
    }

    public function reply(Request $request, PlantReview $review): RedirectResponse
    {
        $data = $request->validate(['shop_reply' => ['nullable', 'string', 'max:2000']]);
        $this->reviews->reply($review, $data['shop_reply'] ?? null);

        return back()->with('success', 'Đã lưu trả lời của shop.');
    }

    public function destroy(PlantReview $review): RedirectResponse
    {
        $this->reviews->delete($review);

        return back()->with('success', 'Đã xóa đánh giá.');
    }
}

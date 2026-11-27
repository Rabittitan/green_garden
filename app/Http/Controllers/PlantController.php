<?php

namespace App\Http\Controllers;

use App\Enums\CommentStatus;
use App\Models\Category;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Models\User;
use App\Services\FlashSaleService;
use App\Services\ReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantController extends Controller
{
    // Danh sách cây + bộ lọc (danh mục, giá, ánh sáng, mệnh, từ khóa)
    public function index(Request $request, FlashSaleService $flashSales): View
    {
        // Bắt đầu câu truy vấn, nạp sẵn danh mục
        $query = Plant::with('category')->active();             // chỉ cây đang bán

        // Lọc theo danh mục (nếu URL có ?category=2)
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Lọc theo đặc tính ánh sáng (?light=ua_bong)
        if ($request->filled('light')) {
            $query->where('light', $request->light);
        }

        // Lọc theo mệnh phong thủy (?element=kim)
        if ($request->filled('element')) {
            $query->where('element', $request->element);
        }

        // Lọc theo từ khóa tên cây (?keyword=kim)
        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%'.$request->keyword.'%');
        }

        // Lọc theo khoảng giá (?price=100-300). Các khoảng nối tiếp, không chồng nhau: mốc dưới TÍNH, mốc trên KHÔNG tính
        if ($request->filled('price')) {
            switch ($request->price) {
                case 'duoi-100':                                   // dưới 100.000đ
                    $query->where('price', '<', 100000);
                    break;
                case '100-300':                                    // 100.000 <= giá < 300.000
                    $query->where('price', '>=', 100000)->where('price', '<', 300000);
                    break;
                case '300-500':                                    // 300.000 <= giá < 500.000
                    $query->where('price', '>=', 300000)->where('price', '<', 500000);
                    break;
                case 'tren-500':                                   // giá >= 500.000
                    $query->where('price', '>=', 500000);
                    break;
            }
        }

        // Lấy kết quả, mỗi trang 9 cây; withQueryString giữ lại bộ lọc khi bấm sang trang 2, 3...
        $plants = $query->latest()->paginate(9)->withQueryString();

        // Lấy danh mục để vẽ ô lọc bên trái
        $categories = Category::active()->orderBy('sort_order')->get();

        // Flash sale đang chạy cho các cây của trang này (1 câu SQL)
        $flashMap = $flashSales->activeItems($plants->pluck('id')->all());

        // Gửi sang view
        return view('plants.index', compact('plants', 'categories', 'flashMap'));
    }

    // Chi tiết 1 cây
    public function show(int $id, ReviewService $reviewService, FlashSaleService $flashSales): View
    {
        $plant = Plant::active()->findOrFail($id);                     // cây ngừng bán -> 404
        $user = auth()->user();

        // Cây cùng mệnh (nạp sẵn danh mục: tránh N+1 trong partial)
        $related = Plant::active()->where('element', $plant->element)
            ->where('id', '!=', $plant->id)->take(4)->get();
        // Nạp danh mục cho cây chính + cây liên quan trong CÙNG 1 câu (trang này sát ngân sách 15 câu SQL)
        (new EloquentCollection([$plant, ...$related->all()]))->load('category');

        $reviews = $plant->approvedReviews()->orderByDesc('helpful_count')->latest('id')
            ->simplePaginate(5, ['*'], 'review_page')->withQueryString();       // không cần COUNT: tổng đã có ở rating_count
        $distribution = $plant->approvedReviews()->selectRaw('rating, COUNT(*) c')->groupBy('rating')->pluck('c', 'rating');

        $comments = $plant->comments()->whereNull('parent_id')->where('status', CommentStatus::Published->value)
            ->with(['replies' => fn ($q) => $q->where('status', CommentStatus::Published->value)])
            ->orderByDesc('id')->simplePaginate(10, ['*'], 'qa_page')->withQueryString();
        // Người viết đánh giá + hỏi + shop trả lời: nạp trong 1 câu thay vì 3 (trang này sát ngân sách 15 câu SQL)
        $replies = $comments->getCollection()->flatMap(fn ($c) => $c->replies);
        $authored = (new EloquentCollection([...$reviews->items(), ...$comments->items(), ...$replies->all()]));
        $authors = User::whereIn('id', $authored->pluck('user_id')->unique()->values())->get()->keyBy('id');
        $authored->each(fn ($m) => $m->setRelation('user', $authors->get($m->user_id)));

        $flashMap = $flashSales->activeItems([$plant->id, ...$related->pluck('id')->all()]);

        return view('plants.show', [
            'flash' => $flashMap->get($plant->id), 'flashMap' => $flashMap,
            'plant' => $plant, 'related' => $related, 'reviews' => $reviews, 'distribution' => $distribution, 'comments' => $comments,
            'myReview' => $user ? PlantReview::where('user_id', $user->id)->where('plant_id', $plant->id)->first() : null,
            'canReview' => $user && $reviewService->proofOfPurchase($user, $plant->id) !== null,
            'votedIds' => $user && $reviews->isNotEmpty() ? DB::table('review_votes')->where('user_id', $user->id)
                ->whereIn('plant_review_id', $reviews->pluck('id'))->pluck('plant_review_id')->all() : [],
        ]);
    }
}

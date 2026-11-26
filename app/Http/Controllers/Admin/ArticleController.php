<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Plant;
use App\Services\ArticleService;
use App\Services\ImageStorage;
use App\Support\Format;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Quản lý tin tức: nhân viên + quản trị viên đăng bài, ảnh bìa, ảnh trong bài, hẹn giờ, gắn cây liên quan. */
class ArticleController extends Controller
{
    public function __construct(private ArticleService $articles) {}

    public function index(Request $request): View
    {
        $filter = $request->validate(['status' => ['nullable', Rule::in(['draft', 'published', 'scheduled'])], 'q' => ['nullable', 'string', 'max:100']]);
        $articles = Article::with('author')->withCount('comments')
            ->when(($filter['status'] ?? null) === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when(($filter['status'] ?? null) === 'published', fn ($q) => $q->published())
            ->when(($filter['status'] ?? null) === 'scheduled', fn ($q) => $q->where('status', 'published')->where('published_at', '>', now()))
            ->when(filled($filter['q'] ?? null), fn ($q) => $q->where('title', 'like', '%'.$filter['q'].'%'))
            ->orderByRaw("status = 'draft' DESC")->latest('published_at')->latest('id')
            ->paginate(15)->withQueryString();

        return view('admin.articles.index', compact('articles'));
    }

    public function create(): View
    {
        return view('admin.articles.create', ['article' => new Article(['status' => 'draft']), 'plants' => $this->plants()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $article = $this->articles->create($request->user(), $data, $data['plant_ids'] ?? [], $request->file('cover'));
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.articles.edit', $article)->with('success', $this->savedMessage($article));
    }

    /** Xem trước (kể cả nháp / hẹn giờ) đúng như khách sẽ thấy. */
    public function show(Article $article): View
    {
        return view('admin.articles.show', ['article' => $article->load(['author', 'plants'])]);
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.edit', ['article' => $article->load('plants'), 'plants' => $this->plants()]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $this->articles->update($article, $data, $data['plant_ids'] ?? [], $request->file('cover'), $request->boolean('remove_cover'));
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.articles.edit', $article)->with('success', $this->savedMessage($article->refresh()));
    }

    public function destroy(Article $article): RedirectResponse
    {
        try {
            $this->articles->delete($article);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.articles.index')->with('success', "Đã xóa bài \"{$article->title}\".");
    }

    /** Nút "Chèn ảnh" trong trình soạn: tải 1 ảnh, trả về đường dẫn + đoạn Markdown để chèn vào nội dung. */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', ...ImageStorage::RULES]], [], ['image' => 'ảnh']);
        try {
            $url = $this->articles->storeImage($request->file('image'));
        } catch (BusinessException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['url' => $url, 'markdown' => '![Mô tả ảnh]('.$url.')']);
    }

    /** Nút "Xem trước" trong trình soạn: hiện nội dung đang gõ (kể cả ảnh vừa chèn) đúng như khách sẽ thấy. Không lưu gì. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['content' => ['nullable', 'string', 'max:20000']], [], ['content' => 'nội dung']);

        return response()->json(['html' => (string) Article::renderMarkdown((string) ($data['content'] ?? ''))]);
    }

    /**
     * @return array{title: string, summary: string, content: string, status: string, published_at?: string|null, image_url?: string|null, plant_ids?: array<int, int>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:300'],
            // Cột TEXT 65.535 byte; tiếng Việt tới 3 byte/ký tự -> 20.000 ký tự là an toàn
            'content' => ['required', 'string', 'max:20000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', ...ImageStorage::RULES],
            'image_url' => ['nullable', 'url:https', 'max:255'],
            'plant_ids' => ['nullable', 'array'], 'plant_ids.*' => ['integer', 'exists:plants,id'],
        ], [], [
            'title' => 'tiêu đề', 'summary' => 'tóm tắt', 'content' => 'nội dung', 'status' => 'trạng thái',
            'published_at' => 'giờ đăng', 'cover' => 'ảnh bìa', 'image_url' => 'link ảnh bìa', 'plant_ids' => 'cây liên quan',
        ]);
        /** @var array{title: string, summary: string, content: string, status: string, published_at?: string|null, image_url?: string|null, plant_ids?: array<int, int>} $fields */
        $fields = Arr::except($data, ['cover']);                            // ảnh bìa lấy riêng bằng $request->file()

        return $fields;
    }

    private function savedMessage(Article $article): string
    {
        return match ($article->statusLabel()) {
            'Nháp' => 'Đã lưu nháp. Bài chưa hiện ở trang tin tức.',
            'Hẹn giờ' => 'Đã hẹn giờ: bài tự hiện lúc '.Format::datetime($article->published_at).'.',
            default => 'Đã đăng bài.',
        };
    }

    /** @return Collection<int, Plant> */
    private function plants()
    {
        return Plant::active()->orderBy('name')->get(['id', 'name']);
    }
}

<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tin tức (bài "Bí quyết chăm cây"). Nhân viên và quản trị viên đều đăng được.
 *  - Trạng thái: nháp | đăng. Bài "đăng" có published_at ở tương lai = hẹn giờ (Article::published() tự ẩn tới giờ).
 *  - Ảnh tải lên lưu ở disk "public" (storage/app/public/articles/Năm/Tháng/…), cột image lưu đường dẫn TƯƠNG ĐỐI
 *    "/storage/…" để không phụ thuộc APP_URL. Ảnh link ngoài (https://…) vẫn dùng được như trước.
 */
class ArticleService
{
    public const IMAGE_DIR = 'articles';

    public function __construct(private ImageStorage $images) {}

    /**
     * @param  array{title: string, summary: string, content: string, status: string, published_at?: string|null, image_url?: string|null}  $data
     * @param  array<int, int>  $plantIds
     */
    public function create(User $author, array $data, array $plantIds = [], ?UploadedFile $cover = null): Article
    {
        $stored = $cover ? $this->storeImage($cover) : null;
        try {
            return DB::transaction(function () use ($author, $data, $plantIds, $stored) {
                $article = new Article($this->attributes($data, null));
                $article->author_id = $author->id;
                $article->slug = $this->uniqueSlug($data['title']);
                $article->image = $stored ?? $this->externalUrl($data['image_url'] ?? null);
                $article->save();
                $article->plants()->sync($plantIds);

                return $article;
            }, 3);
        } catch (\Throwable $e) {
            $this->deleteStored($stored);                                     // lưu bài lỗi: không để lại ảnh mồ côi

            throw $e;
        }
    }

    /**
     * @param  array{title: string, summary: string, content: string, status: string, published_at?: string|null, image_url?: string|null}  $data
     * @param  array<int, int>  $plantIds
     */
    public function update(Article $article, array $data, array $plantIds = [], ?UploadedFile $cover = null, bool $removeCover = false): Article
    {
        $stored = $cover ? $this->storeImage($cover) : null;
        $old = $article->image;
        $oldInline = $this->inlineImages($article->content);
        try {
            DB::transaction(function () use ($article, $data, $plantIds, $stored, $removeCover) {
                $article->fill($this->attributes($data, $article));
                if ($stored) {
                    $article->image = $stored;
                } elseif ($removeCover) {
                    $article->image = null;
                } elseif (array_key_exists('image_url', $data) && filled($data['image_url'])) {
                    $article->image = $this->externalUrl($data['image_url']);
                }
                $article->save();
                $article->plants()->sync($plantIds);
            }, 3);
        } catch (\Throwable $e) {
            $this->deleteStored($stored);

            throw $e;
        }
        if ($old !== $article->image) {
            $this->deleteStored($old);                                        // ảnh bìa cũ do shop tải lên: xóa file
        }
        // Ảnh chèn trong bài bị gỡ khỏi nội dung: xóa file nếu không bài nào khác còn dùng
        $this->deleteUnusedInline(array_diff($oldInline, $this->inlineImages($article->content)), $article);

        return $article;
    }

    /** Chỉ xóa hẳn bài chưa có bình luận; bài đã có bình luận thì chuyển về nháp để gỡ khỏi trang. */
    public function delete(Article $article): void
    {
        if ($article->comments()->withTrashed()->exists()) {
            throw new BusinessException('Bài đã có bình luận nên không xóa hẳn được. Hãy chuyển bài về "Nháp" để gỡ khỏi trang tin tức.');
        }
        $image = $article->image;
        $inline = $this->inlineImages($article->content);
        $article->delete();                                                   // article_plant xóa theo khóa ngoại cascade
        $this->deleteStored($image);
        $this->deleteUnusedInline($inline, $article);
    }

    /** Lưu 1 ảnh (bìa hoặc chèn trong bài), trả về đường dẫn công khai "/storage/articles/…". */
    public function storeImage(UploadedFile $file): string
    {
        return $this->images->store($file, self::IMAGE_DIR);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?Article $article): array
    {
        $publish = $data['status'] === 'published';
        // Ô giờ đăng nhập theo giờ Việt Nam; DB lưu UTC
        $at = filled($data['published_at'] ?? null)
            ? Carbon::parse((string) $data['published_at'], (string) config('shop.display_timezone'))->utc() : null;

        return [
            'title' => $data['title'], 'summary' => $data['summary'], 'content' => $data['content'], 'status' => $data['status'],
            // Đăng mà không chọn giờ: đăng ngay (giữ giờ đăng cũ nếu bài đã đăng trước đó)
            'published_at' => $at ?? ($publish ? ($article->published_at ?? now()) : null),
        ];
    }

    private function externalUrl(?string $url): ?string
    {
        return filled($url) && preg_match('#^https://#i', (string) $url) ? $url : null;
    }

    /**
     * Ảnh do shop tải lên được chèn trong nội dung bài ("/storage/articles/Năm/Tháng/<uuid>.<đuôi>").
     *
     * @return list<string>
     */
    private function inlineImages(?string $content): array
    {
        preg_match_all('#/storage/'.self::IMAGE_DIR.'/\d{4}/\d{2}/[0-9a-f-]{36}\.(?:jpg|png|webp|gif)#i', (string) $content, $m);

        return array_values(array_unique($m[0]));
    }

    /**
     * Xóa file các ảnh trong bài KHÔNG còn bài nào dùng (nội dung hoặc ảnh bìa của bài khác, hay ảnh bìa của chính bài này).
     *
     * @param  array<int, string>  $paths
     */
    private function deleteUnusedInline(array $paths, Article $article): void
    {
        foreach ($paths as $path) {
            $inUse = $path === $article->image || Article::where('id', '<>', $article->id)
                ->where(fn ($q) => $q->where('content', 'like', '%'.$path.'%')->orWhere('image', $path))->exists();
            if (! $inUse) {
                $this->deleteStored($path);
            }
        }
    }

    private function deleteStored(?string $publicPath): void
    {
        $this->images->delete($publicPath, self::IMAGE_DIR);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 200, '') ?: 'bai-viet';
        $slug = $base;
        for ($i = 2; Article::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}

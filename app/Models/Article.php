<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $author_id
 * @property string $title
 * @property string $slug
 * @property string $summary
 * @property string $content
 * @property string|null $image
 * @property string $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Article extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'content', 'image', 'author_id', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsToMany<Plant, $this> */
    public function plants(): BelongsToMany
    {
        return $this->belongsToMany(Plant::class);
    }   // cây được nhắc tới trong bài

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Nội dung bài -> HTML an toàn. Soạn bằng Markdown (đậm, tiêu đề, danh sách, ảnh ![mô tả](đường dẫn), link).
     * HTML gõ tay bị BỎ (html_input=strip), link javascript:/vbscript:/file: bị chặn; xuống 1 dòng = <br> như trước
     * (bài cũ là chữ thuần, các đoạn cách nhau 1 dòng trống, hiển thị không đổi).
     */
    public function renderedContent(): HtmlString
    {
        $html = Str::markdown($this->content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);

        // Ảnh trong bài: tải chậm, không tràn khung
        return new HtmlString(str_replace('<img ', '<img loading="lazy" class="img-fluid rounded-3" ', $html));
    }

    /** Đã bấm "Đăng" nhưng chưa tới giờ hẹn. */
    public function isScheduled(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'draft' => 'Nháp',
            $this->isScheduled() => 'Hẹn giờ',
            default => 'Đã đăng',
        };
    }

    /**
     * Chỉ bài đã xuất bản và đã tới giờ đăng.
     *
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('published_at', '<=', now());
    }
}

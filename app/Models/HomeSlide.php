<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Slide ảnh đầu trang chủ.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $subtitle
 * @property string $image
 * @property string|null $link_url
 * @property string|null $button_label
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HomeSlide extends Model
{
    /** Số slide tối đa (trang chủ chỉ hiện slide đang bật, theo thứ tự). */
    public const MAX = 10;

    protected $fillable = ['title', 'subtitle', 'image', 'link_url', 'button_label', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * Slide đang bật, theo thứ tự hiển thị.
     *
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeShown(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}

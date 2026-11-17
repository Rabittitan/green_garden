<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function plants()   { return $this->hasMany(Plant::class); }
    public function parent()   { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children() { return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order'); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    /**
     * id của danh mục này + mọi danh mục con cháu (mã giảm giá theo danh mục áp cả danh mục con).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function withDescendantIds(array $ids): array
    {
        $all = $frontier = array_map('intval', $ids);
        while ($frontier) {
            $children = static::whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $frontier = array_values(array_diff($children, $all));   // diff: chặn vòng lặp nếu dữ liệu lỗi
            $all = array_merge($all, $frontier);
        }

        return $all;
    }
}

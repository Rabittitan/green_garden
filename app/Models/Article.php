<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'content', 'image', 'author_id', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function plants()
    {
        return $this->belongsToMany(Plant::class);
    }   // cây được nhắc tới trong bài

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** Chỉ bài đã xuất bản và đã tới giờ đăng. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('published_at', '<=', now());
    }
}

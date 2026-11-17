<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'parent_id', 'content', 'ip_address'];

    protected function casts(): array
    {
        return ['status' => CommentStatus::class, 'is_pinned' => 'boolean'];
    }

    public function commentable() { return $this->morphTo(); }        // Plant hoặc Article
    public function user()        { return $this->belongsTo(User::class); }
    public function parent()      { return $this->belongsTo(Comment::class, 'parent_id'); }
    public function replies()     { return $this->hasMany(Comment::class, 'parent_id')->oldest(); }
}

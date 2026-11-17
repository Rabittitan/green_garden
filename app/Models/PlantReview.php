<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlantReview extends Model
{
    use SoftDeletes;

    // status, moderated_*, shop_reply, helpful_count không cho khách gán
    protected $fillable = ['plant_id', 'user_id', 'order_item_id', 'rating', 'title', 'content'];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'moderated_at' => 'datetime',
            'shop_replied_at' => 'datetime',
        ];
    }

    public function plant()     { return $this->belongsTo(Plant::class)->withTrashed(); }
    public function user()      { return $this->belongsTo(User::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function images()    { return $this->hasMany(ReviewImage::class)->orderBy('sort_order'); }
    public function voters()    { return $this->belongsToMany(User::class, 'review_votes')->withPivot('created_at'); }

    /** Huy hiệu "Đã mua hàng". */
    public function getIsVerifiedPurchaseAttribute(): bool { return $this->order_item_id !== null; }
}

<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plant_id
 * @property int $user_id
 * @property int $order_item_id
 * @property int $rating
 * @property string|null $title
 * @property string|null $content
 * @property ReviewStatus $status
 * @property string|null $reject_reason
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property string|null $shop_reply
 * @property Carbon|null $shop_replied_at
 * @property int $helpful_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
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

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return HasMany<ReviewImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<User, $this> */
    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_votes')->withPivot('created_at');
    }
}

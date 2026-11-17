<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plant_review_id
 * @property string $path
 * @property int $sort_order
 * @property Carbon|null $created_at
 */
class ReviewImage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['plant_review_id', 'path', 'sort_order'];

    /** @return BelongsTo<PlantReview, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(PlantReview::class, 'plant_review_id');
    }
}

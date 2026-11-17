<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['plant_review_id', 'path', 'sort_order'];

    public function review() { return $this->belongsTo(PlantReview::class, 'plant_review_id'); }
}

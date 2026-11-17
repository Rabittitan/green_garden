<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_request_id
 * @property int $plant_id
 * @property int $quantity_requested
 * @property int|null $quantity_approved
 * @property int $quantity_received
 * @property int $estimated_unit_cost
 * @property int|null $stock_at_request
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'plant_id', 'quantity_requested', 'quantity_approved',
        'estimated_unit_cost', 'stock_at_request', 'note',
    ];

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    /** Số còn phải nhận thêm. */
    public function getRemainingAttribute(): int
    {
        return max(0, ($this->quantity_approved ?? $this->quantity_requested) - $this->quantity_received);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'plant_id', 'quantity_requested', 'quantity_approved',
        'estimated_unit_cost', 'stock_at_request', 'note',
    ];

    public function request() { return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id'); }
    public function plant()   { return $this->belongsTo(Plant::class)->withTrashed(); }

    /** Số còn phải nhận thêm. */
    public function getRemainingAttribute(): int
    {
        return max(0, ($this->quantity_approved ?? $this->quantity_requested) - $this->quantity_received);
    }
}

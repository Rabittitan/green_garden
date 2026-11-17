<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'code', 'supplier_id', 'requested_by', 'status', 'priority', 'reason', 'needed_by',
        'estimated_total', 'approved_by', 'approved_at', 'rejected_reason', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'needed_by' => 'date',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function supplier()      { return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function requester()     { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver()      { return $this->belongsTo(User::class, 'approved_by'); }
    public function items()         { return $this->hasMany(PurchaseRequestItem::class); }
    public function goodsReceipts() { return $this->hasMany(GoodsReceipt::class); }
}

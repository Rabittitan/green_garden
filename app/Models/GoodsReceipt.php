<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'code', 'purchase_request_id', 'supplier_id', 'status', 'supplier_invoice_no',
        'total_cost', 'received_by', 'received_at', 'note',
    ];

    protected function casts(): array
    {
        return ['status' => GoodsReceiptStatus::class, 'received_at' => 'datetime'];
    }

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function supplier()        { return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function receiver()        { return $this->belongsTo(User::class, 'received_by'); }
    public function items()           { return $this->hasMany(GoodsReceiptItem::class); }
    public function stockMovements()  { return $this->morphMany(StockMovement::class, 'reference'); }
}

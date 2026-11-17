<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'purchase_request_item_id', 'plant_id',
        'quantity_received', 'quantity_rejected', 'unit_cost', 'note',
    ];

    public function receipt()
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function purchaseRequestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}

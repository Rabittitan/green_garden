<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Sổ kho: chỉ ghi thêm, không sửa, không xóa. Sai thì ghi bút toán điều chỉnh.
 * Chứng từ gốc: order_item_id (bán / hủy / hoàn) hoặc goods_receipt_item_id (nhập hàng).
 */
class StockMovement extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'plant_id', 'type', 'quantity', 'stock_after', 'unit_cost',
        'order_item_id', 'goods_receipt_item_id', 'created_by', 'note',
    ];

    protected function casts(): array
    {
        return ['type' => StockMovementType::class, 'quantity' => 'integer', 'stock_after' => 'integer', 'unit_cost' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('stock_movements là sổ chỉ-ghi-thêm, không được sửa.'));
        static::deleting(fn () => throw new LogicException('stock_movements là sổ chỉ-ghi-thêm, không được xóa.'));
    }

    public function plant()            { return $this->belongsTo(Plant::class)->withTrashed(); }
    public function orderItem()        { return $this->belongsTo(OrderItem::class); }
    public function goodsReceiptItem() { return $this->belongsTo(GoodsReceiptItem::class); }
    public function creator()          { return $this->belongsTo(User::class, 'created_by'); }
}

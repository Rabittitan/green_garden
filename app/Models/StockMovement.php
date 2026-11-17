<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Sổ kho: chỉ ghi thêm, không sửa, không xóa. Sai thì ghi bút toán điều chỉnh.
 * Chứng từ gốc: order_item_id (bán / hủy / hoàn) hoặc goods_receipt_item_id (nhập hàng).
 *
 * @property int $id
 * @property int $plant_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property int $stock_after
 * @property int|null $unit_cost
 * @property int|null $order_item_id
 * @property int|null $goods_receipt_item_id
 * @property int|null $created_by
 * @property string|null $note
 * @property Carbon|null $created_at
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

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<GoodsReceiptItem, $this> */
    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

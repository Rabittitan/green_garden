<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Lịch sử một chậu: chỉ ghi thêm. Bút toán kho đi kèm (nếu có) cho biết chứng từ gốc.
 *
 * @property int $id
 * @property int $plant_unit_id
 * @property string $type
 * @property int|null $stock_movement_id
 * @property int|null $order_item_id
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read StockMovement|null $movement
 * @property-read OrderItem|null $orderItem
 * @property-read User|null $creator
 */
class PlantUnitEvent extends Model
{
    const UPDATED_AT = null;

    public const LABELS = [
        'created' => 'Nhập kho / phát mã', 'shipped' => 'Giao cho đơn', 'returned' => 'Hoàn về kho',
        'damaged' => 'Xuất hủy (lỗi)', 'lost' => 'Kiểm kê: thất lạc', 'found' => 'Kiểm kê: tìm thấy lại',
    ];

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('plant_unit_events chỉ ghi thêm, không được sửa.'));
        static::deleting(fn () => throw new LogicException('plant_unit_events chỉ ghi thêm, không được xóa.'));
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }
}

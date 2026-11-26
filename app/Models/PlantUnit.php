<?php

namespace App\Models;

use App\Enums\PlantUnitStatus;
use App\Support\UnitCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Một chậu cây cụ thể, có mã riêng in trên tem QR. Chỉ PlantUnitService đổi trạng thái (cùng transaction với sổ kho).
 *
 * @property int $id
 * @property int $plant_id
 * @property string $code
 * @property PlantUnitStatus $status
 * @property int|null $goods_receipt_item_id
 * @property int|null $order_item_id
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plant $plant
 * @property-read GoodsReceiptItem|null $receiptItem
 * @property-read OrderItem|null $orderItem
 */
class PlantUnit extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => PlantUnitStatus::class, 'status_changed_at' => 'datetime'];
    }

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    /** @return BelongsTo<GoodsReceiptItem, $this> */
    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return HasMany<PlantUnitEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PlantUnitEvent::class)->orderBy('id');
    }

    public function displayCode(): string
    {
        return UnitCode::format($this->code);
    }
}

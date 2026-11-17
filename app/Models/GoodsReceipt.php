<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int|null $purchase_request_id
 * @property int $supplier_id
 * @property GoodsReceiptStatus $status
 * @property string|null $supplier_invoice_no
 * @property int $total_cost
 * @property int $received_by
 * @property Carbon|null $received_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<GoodsReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /** @return HasManyThrough<StockMovement, GoodsReceiptItem, $this> */
    public function stockMovements(): HasManyThrough
    {
        return $this->hasManyThrough(StockMovement::class, GoodsReceiptItem::class);
    }
}

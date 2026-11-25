<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Phiếu kiểm kê: draft (đang đếm) -> completed (đã chốt, kho đã điều chỉnh) | cancelled.
 *
 * @property int $id
 * @property string $code
 * @property string $status
 * @property int|null $category_id
 * @property string|null $note
 * @property int $created_by
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StockCount extends Model
{
    public const STATUSES = ['draft' => 'Đang kiểm', 'completed' => 'Đã chốt', 'cancelled' => 'Đã hủy'];

    protected $fillable = ['code', 'category_id', 'note'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    /** @return HasMany<StockCountItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Một cây trong đợt flash sale. sold_count chỉ đổi trong FlashSaleService (khóa dòng), = tổng số lượng của các dòng hàng
 * chưa hủy trỏ tới dòng này (shop:reconcile đối soát).
 *
 * @property int $id
 * @property int $flash_sale_id
 * @property int $plant_id
 * @property int $sale_price
 * @property int|null $quantity_limit
 * @property int $sold_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FlashSaleItem extends Model
{
    protected $fillable = ['plant_id', 'sale_price', 'quantity_limit'];

    protected function casts(): array
    {
        return ['sale_price' => 'integer', 'quantity_limit' => 'integer', 'sold_count' => 'integer'];
    }

    /** @return BelongsTo<FlashSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    /** Số suất còn lại (null = không giới hạn). */
    public function remaining(): ?int
    {
        return $this->quantity_limit === null ? null : max(0, $this->quantity_limit - $this->sold_count);
    }

    public function covers(int $quantity): bool
    {
        return $this->quantity_limit === null || $this->remaining() >= $quantity;
    }
}

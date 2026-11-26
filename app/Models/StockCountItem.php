<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stock_count_id
 * @property int $plant_id
 * @property int $system_qty
 * @property int|null $counted_qty
 * @property int $unit_cost
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StockCountItem extends Model
{
    protected $fillable = ['plant_id', 'system_qty', 'counted_qty', 'unit_cost', 'note'];

    protected function casts(): array
    {
        return ['system_qty' => 'integer', 'counted_qty' => 'integer', 'unit_cost' => 'integer'];
    }

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    /** Chênh lệch = đếm − sổ (âm = thiếu, dương = thừa); null khi chưa đếm. */
    public function variance(): ?int
    {
        return $this->counted_qty === null ? null : $this->counted_qty - $this->system_qty;
    }

    public function varianceValue(): int
    {
        return ($this->variance() ?? 0) * $this->unit_cost;
    }
}

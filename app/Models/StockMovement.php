<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;

/** Sổ kho: chỉ ghi thêm, không sửa, không xóa. Sai thì ghi bút toán điều chỉnh. */
class StockMovement extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'plant_id', 'type', 'quantity', 'stock_after', 'unit_cost',
        'reference_type', 'reference_id', 'created_by', 'note',
    ];

    protected function casts(): array
    {
        return ['type' => StockMovementType::class];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('stock_movements là sổ chỉ-ghi-thêm, không được sửa.'));
        static::deleting(fn () => throw new \LogicException('stock_movements là sổ chỉ-ghi-thêm, không được xóa.'));
    }

    public function plant()     { return $this->belongsTo(Plant::class)->withTrashed(); }
    public function reference() { return $this->morphTo(); }   // Order | GoodsReceipt | null
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Một chậu lỗi trên phiếu báo lỗi.
 *
 * @property int $id
 * @property int $defect_report_id
 * @property int $plant_unit_id
 * @property int $plant_id
 * @property string $defect_type
 * @property string|null $note
 * @property int|null $unit_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DefectReport $report
 * @property-read PlantUnit $unit
 * @property-read Plant $plant
 */
class DefectReportItem extends Model
{
    /** Loại lỗi (thứ tự = thứ tự trong ô chọn). */
    public const TYPES = [
        'dead' => 'Cây chết / héo',
        'broken' => 'Dập, gãy cành, vỡ chậu',
        'pest' => 'Sâu bệnh, nấm',
        'supplier' => 'Nhà cung cấp giao lỗi',
        'shipping' => 'Hỏng khi vận chuyển',
        'other' => 'Khác',
    ];

    protected $fillable = ['plant_unit_id', 'plant_id', 'defect_type', 'note'];

    protected function casts(): array
    {
        return ['unit_cost' => 'integer'];
    }

    /** @return BelongsTo<DefectReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DefectReport::class, 'defect_report_id');
    }

    /** @return BelongsTo<PlantUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PlantUnit::class, 'plant_unit_id');
    }

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class)->withTrashed();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->defect_type] ?? $this->defect_type;
    }
}

<?php

namespace App\Models;

use App\Enums\FlashSaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property FlashSaleStatus $status
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $reject_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FlashSale extends Model
{
    protected $fillable = ['name', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'approved_at' => 'datetime', 'status' => FlashSaleStatus::class];
    }

    /** @return HasMany<FlashSaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Đã duyệt và đang trong khung giờ. */
    public function isRunning(): bool
    {
        return $this->status === FlashSaleStatus::Approved && $this->starts_at->isPast() && $this->ends_at->isFuture();
    }

    /** Nhãn hiển thị theo thời gian: "Sắp diễn ra" / "Đang diễn ra" / "Đã kết thúc" cho đợt đã duyệt. */
    public function phaseLabel(): string
    {
        if ($this->status !== FlashSaleStatus::Approved) {
            return $this->status->label();
        }

        return match (true) {
            $this->starts_at->isFuture() => 'Sắp diễn ra',
            $this->ends_at->isPast() => 'Đã kết thúc',
            default => 'Đang diễn ra',
        };
    }
}

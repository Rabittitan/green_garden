<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Phiếu báo hàng lỗi: nhân viên lập (quét từng chậu lỗi), quản trị viên khác người lập duyệt thì mới xuất hủy khỏi kho.
 *
 * @property int $id
 * @property string $code
 * @property string $status submitted | approved | rejected
 * @property string|null $note
 * @property list<string>|null $photos
 * @property int $created_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $reject_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $creator
 * @property-read User|null $decider
 * @property-read Collection<int, DefectReportItem> $items
 */
class DefectReport extends Model
{
    public const STATUSES = ['submitted' => ['Chờ duyệt', 'bg-warning text-dark'], 'approved' => ['Đã duyệt, đã xuất hủy', 'bg-success'], 'rejected' => ['Từ chối', 'bg-secondary']];

    protected $fillable = ['code', 'note', 'photos'];

    protected function casts(): array
    {
        return ['photos' => 'array', 'decided_at' => 'datetime'];
    }

    /** @return HasMany<DefectReportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DefectReportItem::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-secondary';
    }
}

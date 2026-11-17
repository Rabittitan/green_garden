<?php

namespace App\Models;

use App\Enums\TierChangeReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Lịch sử đổi hạng: chỉ ghi thêm.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $from_tier_id
 * @property int $to_tier_id
 * @property TierChangeReason $reason
 * @property int $spend_total
 * @property int|null $order_id
 * @property int|null $changed_by
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class MembershipTierHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'from_tier_id', 'to_tier_id', 'reason', 'spend_total', 'order_id', 'changed_by', 'note'];

    protected function casts(): array
    {
        return ['reason' => TierChangeReason::class, 'spend_total' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MembershipTier, $this> */
    public function fromTier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'from_tier_id');
    }

    /** @return BelongsTo<MembershipTier, $this> */
    public function toTier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'to_tier_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

<?php

namespace App\Models;

use App\Enums\TierChangeReason;
use Illuminate\Database\Eloquent\Model;

/** Lịch sử đổi hạng: chỉ ghi thêm. */
class MembershipTierHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'from_tier_id', 'to_tier_id', 'reason', 'spend_total', 'order_id', 'changed_by', 'note'];

    protected function casts(): array
    {
        return ['reason' => TierChangeReason::class, 'spend_total' => 'integer'];
    }

    public function user()      { return $this->belongsTo(User::class); }
    public function fromTier()  { return $this->belongsTo(MembershipTier::class, 'from_tier_id'); }
    public function toTier()    { return $this->belongsTo(MembershipTier::class, 'to_tier_id'); }
    public function order()     { return $this->belongsTo(Order::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}

<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Lịch sử trạng thái đơn: chỉ ghi thêm.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property ActorType $actor_type
 * @property int|null $changed_by
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class OrderStatusHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'actor_type', 'changed_by', 'note'];

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class, 'actor_type' => ActorType::class];
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

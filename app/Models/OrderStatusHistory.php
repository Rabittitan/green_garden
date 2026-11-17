<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

/** Lịch sử trạng thái đơn: chỉ ghi thêm. */
class OrderStatusHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'actor_type', 'changed_by', 'note'];

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class, 'actor_type' => ActorType::class];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

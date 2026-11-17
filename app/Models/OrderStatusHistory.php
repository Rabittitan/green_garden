<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    const UPDATED_AT = null;   // bảng chỉ ghi thêm

    protected $fillable = ['order_id', 'from_status', 'to_status', 'changed_by', 'note'];

    public function order()     { return $this->belongsTo(Order::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'plant_id', 'plant_name', 'plant_sku', 'quantity', 'price', 'discount_amount'];

    public function order()  { return $this->belongsTo(Order::class); }
    public function plant()  { return $this->belongsTo(Plant::class)->withTrashed(); } // cây đã xóa mềm vẫn hiện trong đơn cũ
    public function review() { return $this->hasOne(PlantReview::class); }

    public function getLineTotalAttribute(): int
    {
        return $this->price * $this->quantity - $this->discount_amount;
    }
}

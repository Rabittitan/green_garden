<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 1 lần thu (charge) hoặc hoàn (refund). Chỉ PaymentService ghi.
 * pending_charge_key / success_charge_key là cột sinh (UNIQUE): KHÔNG được ghi.
 */
class Payment extends Model
{
    protected $guarded = ['id', 'pending_charge_key', 'success_charge_key'];

    protected $hidden = ['pending_charge_key', 'success_charge_key'];

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'method' => PaymentMethod::class,
            'status' => PaymentState::class,
            'amount' => 'integer',
            'raw_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order()   { return $this->belongsTo(Order::class); }
    public function parent()  { return $this->belongsTo(Payment::class, 'parent_payment_id'); }
    public function refunds() { return $this->hasMany(Payment::class, 'parent_payment_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeSucceeded(Builder $q): Builder { return $q->where('status', PaymentState::Succeeded->value); }
    public function scopePending(Builder $q): Builder   { return $q->where('status', PaymentState::Pending->value); }

    public function isCharge(): bool { return $this->type === PaymentType::Charge; }
}

<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 1 lần thu (charge) hoặc hoàn (refund). Chỉ PaymentService ghi.
 * pending_charge_key / success_charge_key là cột sinh (UNIQUE): KHÔNG được ghi.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $parent_payment_id
 * @property PaymentType $type
 * @property PaymentMethod $method
 * @property int $amount
 * @property PaymentState $status
 * @property string $txn_ref
 * @property string|null $provider_txn_no
 * @property string|null $provider_response_code
 * @property array<string, mixed>|null $raw_payload
 * @property Carbon|null $paid_at
 * @property int|null $created_by
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $pending_charge_key
 * @property int|null $success_charge_key
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'parent_payment_id');
    }

    /** @return HasMany<Payment, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Payment::class, 'parent_payment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeSucceeded(Builder $q): Builder
    {
        return $q->where('status', PaymentState::Succeeded->value);
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', PaymentState::Pending->value);
    }

    public function isCharge(): bool
    {
        return $this->type === PaymentType::Charge;
    }
}

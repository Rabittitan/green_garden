<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Đơn hàng. CHỈ OrderService / PaymentService ghi (trong transaction có khóa dòng),
 * nên không có $fillable cho request; dùng $guarded để service gán đủ cột snapshot.
 *
 * total = subtotal - member_discount - coupon_discount + shipping_fee - shipping_discount
 *
 * @property int $id
 * @property string $code
 * @property int $user_id
 * @property string $checkout_token
 * @property OrderStatus $status
 * @property string $recipient_name
 * @property string $phone
 * @property string $province
 * @property string $district
 * @property string $ward
 * @property string $address_line
 * @property string|null $note
 * @property int $membership_tier_id
 * @property int $member_discount_percent
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property int $subtotal
 * @property int $member_discount
 * @property int $coupon_discount
 * @property int $shipping_fee
 * @property int $shipping_discount
 * @property int $total
 * @property PaymentMethod $payment_method
 * @property PaymentStatus $payment_status
 * @property int $paid_amount
 * @property int $refunded_amount
 * @property Carbon|null $payment_expires_at
 * @property string|null $shipping_carrier
 * @property string|null $tracking_code
 * @property Carbon $placed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $spend_recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Order extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'member_discount_percent' => 'integer',
            'subtotal' => 'integer',
            'member_discount' => 'integer',
            'coupon_discount' => 'integer',
            'shipping_fee' => 'integer',
            'shipping_discount' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'refunded_amount' => 'integer',
            'payment_expires_at' => 'datetime',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'spend_recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MembershipTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id');
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class)->withTrashed();
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function charges(): HasMany
    {
        return $this->payments()->where('type', PaymentType::Charge->value);
    }

    /** @return HasMany<Payment, $this> */
    public function refunds(): HasMany
    {
        return $this->payments()->where('type', PaymentType::Refund->value);
    }

    /** @return HasOne<CouponRedemption, $this> */
    public function redemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasManyThrough<StockMovement, OrderItem, $this> */
    public function stockMovements(): HasManyThrough
    {
        return $this->hasManyThrough(StockMovement::class, OrderItem::class);
    }

    /** Charge đang chờ (tối đa 1, DB đảm bảo). */
    public function pendingCharge(): ?Payment
    {
        return $this->charges()->where('status', PaymentState::Pending->value)->first();
    }

    // ---------------------------------------------------------------- số tiền

    /** Tiền hàng sau mọi giảm giá (chưa gồm ship). */
    public function getMerchandiseTotalAttribute(): int
    {
        return $this->subtotal - $this->member_discount - $this->coupon_discount;
    }

    public function getNetShippingFeeAttribute(): int
    {
        return $this->shipping_fee - $this->shipping_discount;
    }

    /** Số tiền đã thu còn chưa hoàn. */
    public function getRefundableAmountAttribute(): int
    {
        return $this->paid_amount - $this->refunded_amount;
    }

    /** Phần đóng góp vào tổng chi tiêu xét hạng: tiền thực trả, trừ phần hoàn, không tính phí ship. */
    public function getSpendContributionAttribute(): int
    {
        return max(0, $this->paid_amount - $this->refunded_amount - $this->net_shipping_fee);
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', [$this->address_line, $this->ward, $this->district, $this->province]);
    }

    // ---------------------------------------------------------------- trạng thái

    public function isPaymentExpired(): bool
    {
        return $this->status === OrderStatus::PendingPayment
            && $this->payment_expires_at !== null
            && $this->payment_expires_at->copy()->addMinutes((int) config('shop.payment.expire_grace_minutes'))->isPast();
    }

    /** Còn trong hạn đổi trả (đơn hoàn thành, chưa chốt chi tiêu). */
    public function isWithinReturnWindow(): bool
    {
        return $this->status === OrderStatus::Completed
            && $this->spend_recorded_at === null
            && $this->completed_at !== null
            && $this->completed_at->copy()->addDays((int) config('shop.return_window_days'))->isFuture();
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeStatus(Builder $q, OrderStatus ...$statuses): Builder
    {
        return $q->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
    }

    /** Mã đơn công khai: GL + yymmdd (giờ VN) + 6 ký tự ngẫu nhiên. Service thử lại nếu trùng UNIQUE. */
    public static function newCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';           // bỏ 0/O/1/I dễ nhầm khi đọc qua điện thoại
        $rand = '';
        for ($i = 0; $i < 6; $i++) {
            $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return config('shop.order_code_prefix').now(config('shop.display_timezone'))->format('ymd').$rand;
    }
}

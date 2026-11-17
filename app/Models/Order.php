<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Đơn hàng. CHỈ OrderService / PaymentService ghi (trong transaction có khóa dòng),
 * nên không có $fillable cho request; dùng $guarded để service gán đủ cột snapshot.
 *
 * total = subtotal - member_discount - coupon_discount + shipping_fee - shipping_discount
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

    public function user()        { return $this->belongsTo(User::class); }
    public function tier()        { return $this->belongsTo(MembershipTier::class, 'membership_tier_id'); }
    public function coupon()      { return $this->belongsTo(Coupon::class)->withTrashed(); }
    public function items()       { return $this->hasMany(OrderItem::class)->orderBy('id'); }
    public function payments()    { return $this->hasMany(Payment::class)->orderBy('id'); }
    public function charges()     { return $this->payments()->where('type', PaymentType::Charge->value); }
    public function refunds()     { return $this->payments()->where('type', PaymentType::Refund->value); }
    public function redemption()  { return $this->hasOne(CouponRedemption::class); }
    public function histories()   { return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id'); }
    public function stockMovements() { return $this->hasManyThrough(StockMovement::class, OrderItem::class); }

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

<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ĐƠN HÀNG
 *
 * Vòng đời:
 *   pending_payment ─► pending ─► confirmed ─► shipping ─► completed ─(trong hạn đổi trả)─► returning ─► returned
 *          └──────────────┴───────────┴─► cancelled                └────────────────────────────►┘ (từ shipping)
 *
 * Công thức tiền (viết dạng CỘNG trong CHECK để không tràn số UNSIGNED trên MySQL):
 *   total = subtotal - member_discount - coupon_discount + shipping_fee - shipping_discount
 *
 * Mọi thông tin người nhận, hạng, mã giảm giá đều là SNAPSHOT tại lúc đặt.
 * Chứng từ KHÔNG BAO GIỜ bị xóa: mọi FK trỏ vào orders đều RESTRICT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                       // mã công khai, không lộ số thứ tự
            $table->foreignId('user_id')->constrained('users');
            $table->char('checkout_token', 36);                          // chống bấm đúp
            $table->string('status', 20);

            // Người nhận (snapshot)
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->string('province', 100);
            $table->string('district', 100);
            $table->string('ward', 100);
            $table->string('address_line');
            $table->text('note')->nullable();

            // Hạng + mã (snapshot)
            $table->foreignId('membership_tier_id')->constrained('membership_tiers');
            $table->unsignedTinyInteger('member_discount_percent')->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons');
            $table->string('coupon_code', 40)->nullable();

            // Tiền
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('member_discount')->default(0);
            $table->unsignedBigInteger('coupon_discount')->default(0);
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('shipping_discount')->default(0);
            $table->unsignedBigInteger('total');

            // Thanh toán (paid_amount / refunded_amount là cache của bảng payments)
            $table->string('payment_method', 20);
            $table->string('payment_status', 20)->default('unpaid');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->timestamp('payment_expires_at')->nullable();

            // Vận chuyển
            $table->string('shipping_carrier', 30)->nullable();
            $table->string('tracking_code', 50)->nullable();

            // Mốc thời gian
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('spend_recorded_at')->nullable();          // đã cộng vào lifetime_spend của khách
            $table->timestamps();

            $table->unique(['user_id', 'checkout_token'], 'uq_orders_checkout');
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'payment_expires_at'], 'idx_orders_expire');
            $table->index(['status', 'completed_at', 'spend_recorded_at'], 'idx_orders_spend_due');
            $table->index(['user_id', 'spend_recorded_at'], 'idx_orders_user_spend');
        });

        DbCheck::addMany('orders', [
            'chk_o_status' => DbCheck::in('status', ['pending_payment', 'pending', 'confirmed', 'shipping', 'completed', 'returning', 'returned', 'cancelled']),
            'chk_o_pm' => DbCheck::in('payment_method', ['cod', 'vnpay', 'bank_transfer']),
            'chk_o_ps' => DbCheck::in('payment_status', ['unpaid', 'paid', 'refund_pending', 'partially_refunded', 'refunded']),
            // Tiền
            'chk_o_total' => 'total + member_discount + coupon_discount + shipping_discount = subtotal + shipping_fee',
            'chk_o_item_disc' => 'member_discount + coupon_discount <= subtotal',
            'chk_o_ship_disc' => 'shipping_discount <= shipping_fee',
            'chk_o_amounts' => 'refunded_amount <= paid_amount AND paid_amount <= total',
            // Trạng thái thanh toán khớp với số tiền
            'chk_o_ps_unpaid' => "payment_status <> 'unpaid' OR (paid_amount = 0 AND refunded_amount = 0)",
            'chk_o_ps_paid' => "payment_status <> 'paid' OR (paid_amount = total AND refunded_amount = 0)",
            'chk_o_ps_rpend' => "payment_status <> 'refund_pending' OR paid_amount > refunded_amount",
            'chk_o_ps_prefund' => "payment_status <> 'partially_refunded' OR (refunded_amount > 0 AND refunded_amount < paid_amount)",
            'chk_o_ps_refunded' => "payment_status <> 'refunded' OR (refunded_amount = paid_amount AND paid_amount > 0)",
            // Trạng thái đơn khớp với thanh toán / vận chuyển
            'chk_o_no_paid_on_closed' => "status NOT IN ('cancelled','returned') OR payment_status <> 'paid'",
            'chk_o_completed_paid' => "status <> 'completed' OR payment_status <> 'unpaid'",
            'chk_o_pending_payment' => "status <> 'pending_payment' OR (payment_method <> 'cod' AND payment_expires_at IS NOT NULL AND payment_status = 'unpaid')",
            'chk_o_tracking' => "status NOT IN ('shipping','completed','returning','returned') OR tracking_code IS NOT NULL",
            'chk_o_cancel' => "(status = 'cancelled') = (cancelled_at IS NOT NULL)",
            'chk_o_shipped_at' => "status NOT IN ('shipping','completed','returning','returned') OR shipped_at IS NOT NULL",
            'chk_o_completed_at' => "status <> 'completed' OR completed_at IS NOT NULL",
            'chk_o_returned_at' => "(status = 'returned') = (returned_at IS NOT NULL)",
            'chk_o_paid_at' => 'paid_amount = 0 OR paid_at IS NOT NULL',
            'chk_o_spend' => "spend_recorded_at IS NULL OR status = 'completed'",
        ]);

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('plant_id')->constrained('plants');
            $table->string('plant_name', 150);                           // snapshot
            $table->string('plant_sku', 50);                             // snapshot
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_subtotal')->storedAs('unit_price * quantity');
            $table->unsignedBigInteger('member_discount')->default(0);   // phần giảm theo hạng phân bổ cho dòng
            $table->unsignedBigInteger('coupon_discount')->default(0);   // phần giảm theo mã phân bổ cho dòng
            $table->unsignedBigInteger('unit_cost')->default(0);         // giá vốn lúc bán (COGS)
            $table->unsignedInteger('returned_good_qty')->default(0);
            $table->unsignedInteger('returned_damaged_qty')->default(0);
            $table->timestamps();

            $table->unique(['order_id', 'plant_id']);
        });

        DbCheck::addMany('order_items', [
            'chk_oi_qty' => 'quantity > 0',
            'chk_oi_disc' => 'member_discount + coupon_discount <= unit_price * quantity',
            'chk_oi_return' => 'returned_good_qty + returned_damaged_qty <= quantity',
        ]);

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('actor_type', 10);                            // customer | staff | system | gateway
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        DbCheck::add('order_status_histories', 'chk_osh_actor', DbCheck::in('actor_type', ['customer', 'staff', 'system', 'gateway']));

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('order_id')->unique()->constrained('orders'); // 1 đơn tối đa 1 mã
            $table->unsignedBigInteger('discount_amount');                 // coupon_discount + shipping_discount
            $table->string('status', 10)->default('applied');              // applied | released
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'user_id', 'status']);
        });

        DbCheck::addMany('coupon_redemptions', [
            'chk_cr_status' => DbCheck::in('status', ['applied', 'released']),
            'chk_cr_released' => "(status = 'released') = (released_at IS NOT NULL)",
        ]);

        Schema::table('membership_tier_histories', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders');
        });
    }

    public function down(): void
    {
        Schema::table('membership_tier_histories', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

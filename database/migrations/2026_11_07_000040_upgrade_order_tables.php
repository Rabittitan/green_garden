<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nâng cấp đơn hàng để gắn được phiếu giảm giá, phí ship, thanh toán và lịch sử trạng thái.
 *
 * Công thức bất biến của đơn: total = subtotal - discount_amount + shipping_fee
 *
 * order_items lưu SNAPSHOT (plant_name, plant_sku) để hóa đơn cũ không đổi khi
 * admin sửa tên cây; discount_amount là phần giảm giá được phân bổ xuống từng dòng,
 * cần khi hoàn tiền một phần hoặc tính doanh thu theo từng cây.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');   // GL2611070001: mã công khai, không lộ số đơn
            $table->unsignedBigInteger('subtotal')->default(0)->after('note');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('subtotal');
            $table->unsignedInteger('shipping_fee')->default(0)->after('discount_amount');
            $table->foreignId('coupon_id')->nullable()->after('shipping_fee')
                  ->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id'); // snapshot mã đã dùng
            $table->string('payment_method', 20)->default('cod');
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('note')->nullable()->change();                     // string(255) -> text

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('plant_name')->nullable()->after('plant_id');
            $table->string('plant_sku', 50)->nullable()->after('plant_name');
            $table->unsignedInteger('discount_amount')->default(0)->after('price');
            $table->unique(['order_id', 'plant_id']);                       // 1 cây chỉ 1 dòng trong 1 đơn
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete(); // 1 đơn tối đa 1 mã
            $table->unsignedBigInteger('discount_amount');
            $table->string('status', 20)->default('applied');   // applied | released (đơn hủy -> trả lượt)
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'user_id', 'status']);  // đếm lượt dùng của 1 khách
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        // ---- Backfill dữ liệu cũ ----
        DB::table('orders')->update(['subtotal' => DB::raw('total')]);
        DB::table('orders')->whereNull('code')->eachById(function ($o) {
            DB::table('orders')->where('id', $o->id)
                ->update(['code' => 'GL'.str_pad((string) $o->id, 8, '0', STR_PAD_LEFT)]);
        });
        DB::table('order_items')->whereNull('plant_name')->eachById(function ($i) {
            $plant = DB::table('plants')->where('id', $i->plant_id)->first(['name', 'sku']);
            DB::table('order_items')->where('id', $i->id)
                ->update(['plant_name' => $plant?->name, 'plant_sku' => $plant?->sku]);
        });

        DbCheck::add('orders', 'chk_orders_status', DbCheck::in('status', ['pending', 'shipping', 'completed', 'cancelled']));
        DbCheck::add('orders', 'chk_orders_payment_method', DbCheck::in('payment_method', ['cod', 'vnpay', 'momo', 'bank_transfer']));
        DbCheck::add('orders', 'chk_orders_payment_status', DbCheck::in('payment_status', ['unpaid', 'paid', 'refunded']));
        DbCheck::add('orders', 'chk_orders_total', 'total = subtotal - discount_amount + shipping_fee');
        DbCheck::add('orders', 'chk_orders_discount', 'discount_amount <= subtotal + shipping_fee');
        DbCheck::add('order_items', 'chk_order_items_qty', 'quantity > 0');
        DbCheck::add('coupon_redemptions', 'chk_coupon_redemptions_status', DbCheck::in('status', ['applied', 'released']));
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('coupon_redemptions');

        DbCheck::drop('order_items', 'chk_order_items_qty');
        DbCheck::drop('orders', 'chk_orders_discount');
        DbCheck::drop('orders', 'chk_orders_total');
        DbCheck::drop('orders', 'chk_orders_payment_status');
        DbCheck::drop('orders', 'chk_orders_payment_method');
        DbCheck::drop('orders', 'chk_orders_status');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'plant_id']);
            $table->dropColumn(['plant_name', 'plant_sku', 'discount_amount']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'subtotal', 'discount_amount', 'shipping_fee', 'coupon_code',
                'payment_method', 'payment_status', 'paid_at', 'completed_at', 'cancelled_at', 'cancel_reason',
            ]);
        });
    }
};

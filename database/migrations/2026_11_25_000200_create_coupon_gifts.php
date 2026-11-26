<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ví mã giảm giá của khách + tặng mã khi mua (chủ shop duyệt 25/11/2026). CHỈ THÊM.
 *  - coupon_gift_rules: luật tặng tự động ("đơn hoàn thành từ X đ -> tặng mã giảm Y, hạn N ngày").
 *  - coupons.owner_user_id: mã riêng của 1 khách (chỉ chủ ví dùng được); NULL = mã chung như trước.
 *  - coupons.gift_rule_id + source_order_id: mã sinh từ luật nào cho đơn nào; UNIQUE -> mỗi đơn chỉ được tặng 1 lần / luật.
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::create('coupon_gift_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->unsignedBigInteger('min_order_total');                // đơn hoàn thành có tổng tiền >= mức này thì được tặng
            $table->string('type', 20);                                   // percent | fixed | free_shipping (mã được tặng)
            $table->unsignedBigInteger('value')->default(0);
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->unsignedBigInteger('min_order_amount')->default(0);   // điều kiện dùng mã được tặng
            $table->unsignedSmallInteger('valid_days');                    // hạn dùng tính từ lúc tặng
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        DbCheck::addMany('coupon_gift_rules', [
            'chk_gift_type' => DbCheck::in('type', ['percent', 'fixed', 'free_shipping']),
            'chk_gift_value' => "(type = 'percent' AND value BETWEEN 1 AND 100) OR (type = 'fixed' AND value > 0) OR (type = 'free_shipping' AND value = 0)",
            'chk_gift_days' => 'valid_days BETWEEN 1 AND 365',
        ]);

        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('created_by')->constrained('users');
            $table->foreignId('gift_rule_id')->nullable()->after('owner_user_id')->constrained('coupon_gift_rules');
            $table->foreignId('source_order_id')->nullable()->after('gift_rule_id')->constrained('orders');
            $table->unique(['gift_rule_id', 'source_order_id'], 'uq_coupon_gift_once');
            $table->index(['owner_user_id', 'is_active']);
        });
        // Mã sinh từ luật luôn là mã riêng, có đơn nguồn
        DbCheck::add('coupons', 'chk_coupon_gift_owner', 'gift_rule_id IS NULL OR (owner_user_id IS NOT NULL AND source_order_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `coupons` DROP CHECK `chk_coupon_gift_owner`');
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique('uq_coupon_gift_once');
            $table->dropIndex(['owner_user_id', 'is_active']);
            $table->dropConstrainedForeignId('source_order_id');
            $table->dropConstrainedForeignId('gift_rule_id');
            $table->dropConstrainedForeignId('owner_user_id');
        });
        Schema::dropIfExists('coupon_gift_rules');
    }
};

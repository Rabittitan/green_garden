<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHIẾU GIẢM GIÁ ("luật" giảm giá). Lượt dùng thực tế nằm ở coupon_redemptions (migration đơn hàng).
 *
 *  - code luôn IN HOA, KHÔNG tái sử dụng kể cả sau khi xóa mềm (unique bao gồm dòng đã xóa).
 *  - percent: value 1..100, chặn trần bởi max_discount_amount
 *  - fixed: value > 0 (VND)
 *  - free_shipping: value = 0, chỉ áp cho cả đơn
 *  - min_tier_id: chỉ khách có hạng >= hạng này được dùng
 *  - combinable_with_tier = false: không cộng dồn với giảm giá theo hạng,
 *    hệ thống tự chọn phương án có lợi hơn cho khách.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->unsignedBigInteger('value')->default(0);
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->unsignedBigInteger('min_order_amount')->default(0);
            $table->string('scope', 20)->default('order');
            $table->foreignId('min_tier_id')->nullable()->constrained('membership_tiers');
            $table->boolean('combinable_with_tier')->default(true);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('first_order_only')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        DbCheck::addMany('coupons', [
            'chk_coupon_type' => DbCheck::in('type', ['percent', 'fixed', 'free_shipping']),
            'chk_coupon_scope' => DbCheck::in('scope', ['order', 'plants', 'categories']),
            'chk_coupon_value' => "(type = 'percent' AND value BETWEEN 1 AND 100) OR (type = 'fixed' AND value > 0) OR (type = 'free_shipping' AND value = 0)",
            'chk_coupon_freeship_scope' => "type <> 'free_shipping' OR scope = 'order'",
            'chk_coupon_dates' => 'starts_at IS NULL OR ends_at IS NULL OR starts_at < ends_at',
            'chk_coupon_usage' => 'usage_limit IS NULL OR used_count <= usage_limit',
            'chk_coupon_per_user' => 'usage_limit_per_user >= 1',
        ]);

        Schema::create('coupon_plants', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained('coupons');
            $table->foreignId('plant_id')->constrained('plants');
            $table->primary(['coupon_id', 'plant_id']);
        });

        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained('coupons');
            $table->foreignId('category_id')->constrained('categories');
            $table->primary(['coupon_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupon_plants');
        Schema::dropIfExists('coupons');
    }
};

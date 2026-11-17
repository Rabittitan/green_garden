<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHIẾU GIẢM GIÁ
 *
 * Tách 2 khái niệm:
 *  - coupons: "luật" giảm giá (giảm bao nhiêu, cho ai, khi nào, bao nhiêu lượt)
 *  - coupon_redemptions (migration 000040): mỗi lần một đơn hàng THỰC SỰ dùng mã
 *
 * Kiểu giảm (type):
 *  - percent: value = % (1..100), bị chặn trần bởi max_discount_amount
 *  - fixed: value = số tiền VND giảm thẳng
 *  - free_shipping: miễn phí vận chuyển (value bỏ qua), chặn trần bởi max_discount_amount nếu có
 *
 * Phạm vi (scope):
 *  - order: áp cho cả đơn
 *  - plants / categories: chỉ tính trên các dòng hàng thuộc coupon_plants / coupon_categories
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();                  // luôn lưu IN HOA, ứng dụng strtoupper trước khi tra
            $table->string('name');                                 // "Giảm 10% mừng khai trương"
            $table->text('description')->nullable();
            $table->string('type', 20);                             // percent | fixed | free_shipping
            $table->unsignedInteger('value')->default(0);
            $table->unsignedInteger('max_discount_amount')->nullable(); // trần giảm cho loại %
            $table->unsignedInteger('min_order_amount')->default(0);    // đơn tối thiểu
            $table->string('scope', 20)->default('order');          // order | plants | categories
            $table->unsignedInteger('usage_limit')->nullable();     // tổng lượt, NULL = không giới hạn
            $table->unsignedInteger('usage_limit_per_user')->default(1);
            $table->unsignedInteger('used_count')->default(0);      // đếm sẵn, tăng trong transaction có lockForUpdate
            $table->boolean('first_order_only')->default(false);    // chỉ cho khách chưa từng có đơn hoàn thành
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();                                  // xóa mềm: đơn cũ vẫn tham chiếu được

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('coupon_plants', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'plant_id']);
        });

        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        DbCheck::add('coupons', 'chk_coupons_type', DbCheck::in('type', ['percent', 'fixed', 'free_shipping']));
        DbCheck::add('coupons', 'chk_coupons_scope', DbCheck::in('scope', ['order', 'plants', 'categories']));
        DbCheck::add('coupons', 'chk_coupons_percent', "type <> 'percent' OR (value BETWEEN 1 AND 100)");
        DbCheck::add('coupons', 'chk_coupons_dates', 'starts_at IS NULL OR ends_at IS NULL OR starts_at < ends_at');
        DbCheck::add('coupons', 'chk_coupons_usage', 'usage_limit IS NULL OR used_count <= usage_limit');
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupon_plants');
        Schema::dropIfExists('coupons');
    }
};

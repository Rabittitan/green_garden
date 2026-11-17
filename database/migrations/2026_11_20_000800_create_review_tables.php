<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ĐÁNH GIÁ CÂY (khác bình luận): có số sao, chỉ khách đã nhận hàng (đơn completed) mới được viết,
 * order_item_id là bằng chứng mua hàng; mỗi khách 1 đánh giá / 1 cây; qua kiểm duyệt.
 * Bất biến: plants.rating_sum / rating_count = tổng / số review đang approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('plants');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('order_item_id')->constrained('order_items');
            $table->unsignedTinyInteger('rating');
            $table->string('title', 150)->nullable();
            $table->text('content')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('reject_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users');
            $table->timestamp('moderated_at')->nullable();
            $table->text('shop_reply')->nullable();
            $table->timestamp('shop_replied_at')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'plant_id']);
            $table->index(['plant_id', 'status', 'created_at']);
            $table->index(['plant_id', 'status', 'rating']);
        });

        DbCheck::addMany('plant_reviews', [
            'chk_plant_reviews_rating' => 'rating BETWEEN 1 AND 5',
            'chk_plant_reviews_status' => DbCheck::in('status', ['pending', 'approved', 'rejected']),
        ]);

        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_review_id')->constrained('plant_reviews')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('review_votes', function (Blueprint $table) {
            $table->foreignId('plant_review_id')->constrained('plant_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['plant_review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_votes');
        Schema::dropIfExists('review_images');
        Schema::dropIfExists('plant_reviews');
    }
};

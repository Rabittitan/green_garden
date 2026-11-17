<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ĐÁNH GIÁ và BÌNH LUẬN là 2 thứ KHÁC NHAU, không gộp chung 1 bảng:
 *
 *  plant_reviews (đánh giá):
 *   - có số sao 1..5, ảnh hưởng điểm trung bình của cây
 *   - CHỈ khách đã mua và đơn đã hoàn thành mới được viết (order_item_id là bằng chứng mua hàng)
 *   - mỗi khách 1 đánh giá / 1 cây (mua lại thì sửa đánh giá cũ), tránh spam kéo điểm
 *   - qua kiểm duyệt (pending -> approved/rejected), shop được trả lời
 *
 *  comments (bình luận / hỏi đáp):
 *   - không có số sao, ai đăng nhập cũng hỏi được ("cây này để phòng máy lạnh được không?")
 *   - đa hình (polymorphic): dùng chung cho cây (plant) và bài viết (article)
 *   - trả lời lồng 1 cấp qua parent_id
 *
 *  article_plant: nối bài viết "Bí Quyết Chăm Cây" với các cây được nhắc tới,
 *   để trang cây hiện "Bài viết hướng dẫn chăm cây này" và ngược lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('plants');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedTinyInteger('rating');                    // 1..5
            $table->string('title', 150)->nullable();
            $table->text('content')->nullable();
            $table->string('status', 20)->default('pending');        // pending | approved | rejected
            $table->string('reject_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('shop_reply')->nullable();                   // phản hồi của shop
            $table->timestamp('shop_replied_at')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);     // cache của review_votes
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'plant_id']);                  // 1 khách - 1 đánh giá - 1 cây
            $table->index(['plant_id', 'status', 'created_at']);      // trang chi tiết cây: lấy review đã duyệt mới nhất
            $table->index(['plant_id', 'status', 'rating']);          // lọc "chỉ xem 5 sao"
        });

        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_review_id')->constrained('plant_reviews')->cascadeOnDelete();
            $table->string('path');                                   // đường dẫn trong storage, không lưu URL ngoài
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('review_votes', function (Blueprint $table) {      // nút "Hữu ích"
            $table->foreignId('plant_review_id')->constrained('plant_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['plant_review_id', 'user_id']);             // mỗi người vote 1 lần
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');                            // commentable_type ('plant' | 'article') + commentable_id
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->string('status', 20)->default('published');      // published | hidden | pending
            $table->boolean('is_pinned')->default(false);             // ghim câu trả lời của shop
            $table->string('ip_address', 45)->nullable();             // chống spam
            $table->timestamps();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id', 'status', 'created_at'], 'comments_target_status_idx');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('article_plant', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'plant_id']);
            $table->index('plant_id');                                // tra ngược: cây -> bài viết
        });

        DbCheck::add('plant_reviews', 'chk_plant_reviews_rating', 'rating BETWEEN 1 AND 5');
        DbCheck::add('plant_reviews', 'chk_plant_reviews_status', DbCheck::in('status', ['pending', 'approved', 'rejected']));
        DbCheck::add('comments', 'chk_comments_status', DbCheck::in('status', ['published', 'hidden', 'pending']));
    }

    public function down(): void
    {
        Schema::dropIfExists('article_plant');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('review_votes');
        Schema::dropIfExists('review_images');
        Schema::dropIfExists('plant_reviews');
    }
};

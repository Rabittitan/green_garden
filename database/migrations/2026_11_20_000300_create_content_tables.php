<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NỘI DUNG: bài viết, liên kết bài viết - cây, bình luận/hỏi đáp (đa hình: plant | article).
 * (Đánh giá cây nằm ở migration riêng vì cần bảng order_items làm bằng chứng mua hàng.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 300);
            $table->text('content');
            $table->string('image')->nullable();
            $table->string('status', 20)->default('published');          // draft | published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        DbCheck::add('articles', 'chk_articles_status', DbCheck::in('status', ['draft', 'published']));

        Schema::create('article_plant', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'plant_id']);
            $table->index('plant_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');                                // 'plant' | 'article'
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->string('status', 20)->default('published');          // published | hidden | pending
            $table->boolean('is_pinned')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id', 'status', 'created_at'], 'comments_target_status_idx');
            $table->index(['user_id', 'created_at']);
        });

        DbCheck::add('comments', 'chk_comments_status', DbCheck::in('status', ['published', 'hidden', 'pending']));
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('article_plant');
        Schema::dropIfExists('articles');
    }
};

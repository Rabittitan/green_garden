<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bảng bài viết "Bí Quyết Chăm Cây"
        Schema::create('articles', function (Blueprint $table) {
            $table->id();                          // khóa chính tự tăng
            $table->string('title');               // tiêu đề bài viết
            $table->string('summary', 300);        // đoạn mô tả ngắn hiện ở thẻ tin
            $table->text('content');               // nội dung đầy đủ
            $table->string('image')->nullable();   // link ảnh bìa
            $table->timestamps();                  // created_at dùng làm ngày đăng
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
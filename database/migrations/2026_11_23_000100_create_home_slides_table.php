<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slide ảnh ở đầu trang chủ, admin quản lý (chủ shop yêu cầu 23/11/2026).
 * CHỈ THÊM 1 bảng mới, không có khóa ngoại tới bảng nghiệp vụ; không đụng tiền, kho, đơn.
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::create('home_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->nullable();             // dòng chữ lớn trên ảnh (để trống = chỉ hiện ảnh)
            $table->string('subtitle', 200)->nullable();
            $table->string('image');                              // "/storage/slides/…" (tải lên) hoặc https://…
            $table->string('link_url')->nullable();               // "/cay-canh?..." trong site hoặc https://…
            $table->string('button_label', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_slides');
    }
};

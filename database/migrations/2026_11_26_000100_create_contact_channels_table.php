<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kênh liên hệ (Zalo / Facebook / TikTok) cho bong bóng mạng xã hội ở góc trang (chủ shop yêu cầu 26/11/2026).
 * CHỈ THÊM 1 bảng mới, không khóa ngoại tới bảng nghiệp vụ; mỗi kênh tối đa 1 dòng.
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::create('contact_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->unique();             // zalo | facebook | tiktok
            $table->string('url', 255);                          // https://… (host kiểm theo từng kênh ở ContactChannel::HOSTS)
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        DbCheck::addMany('contact_channels', [
            'chk_cc_channel' => DbCheck::in('channel', ['zalo', 'facebook', 'tiktok']),
            'chk_cc_https' => "url LIKE 'https://%'",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_channels');
    }
};

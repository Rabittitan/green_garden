<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiểm kê bằng quét mã từng chậu (chủ shop duyệt 26/11/2026): thay việc gõ số đếm bằng tay. CHỈ THÊM 1 bảng + 1 cột.
 * stock_counts.unit_event_mark = id lớn nhất của plant_unit_events lúc mở phiếu: chậu có sự kiện sau mốc này
 * (vừa nhập, vừa hoàn về, vừa giao...) là chậu "không nằm yên", không bị tính thất lạc.
 * Mỗi chậu chỉ được tính 1 lần trên 1 phiếu (UNIQUE). Chốt phiếu: chậu nằm yên trong kho từ lúc mở phiếu mà không
 * được quét -> thất lạc; chậu đang "thất lạc" mà quét thấy -> về kho (StockCountService::complete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_event_mark')->nullable()->after('category_id');
        });
        Schema::create('stock_count_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts');
            $table->foreignId('plant_unit_id')->constrained('plant_units');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique(['stock_count_id', 'plant_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_scans');
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->dropColumn('unit_event_mark');
        });
    }
};

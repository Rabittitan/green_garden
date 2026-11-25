<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu kiểm kê (chủ shop duyệt 25/11/2026): nghiệp vụ riêng dạng báo cáo, do quản trị viên làm. CHỈ THÊM.
 *  - stock_counts / stock_count_items: chốt số tồn trên sổ lúc lập phiếu, nhập số đếm, ra chênh lệch (số lượng + giá trị).
 *  - stock_movements.stock_count_item_id: bút toán điều chỉnh khi chốt phiếu trỏ về dòng phiếu (sổ kho có FK tới chứng từ).
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                          // KK-2611-0001
            $table->string('status', 20)->default('draft');               // draft | completed | cancelled
            $table->foreignId('category_id')->nullable()->constrained('categories');   // NULL = mọi cây đang bán
            $table->string('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        DbCheck::add('stock_counts', 'chk_sc_status', DbCheck::in('status', ['draft', 'completed', 'cancelled']));

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts');
            $table->foreignId('plant_id')->constrained('plants');
            $table->unsignedInteger('system_qty');                          // tồn trên sổ lúc lập phiếu
            $table->unsignedInteger('counted_qty')->nullable();             // số đếm thực tế (NULL = chưa đếm)
            $table->unsignedBigInteger('unit_cost');                        // giá vốn lúc lập phiếu (định giá chênh lệch)
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'plant_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('stock_count_item_id')->nullable()->after('goods_receipt_item_id')->constrained('stock_count_items');
        });
        DbCheck::add('stock_movements', 'chk_sm_count_ref', "stock_count_item_id IS NULL OR type = 'adjustment'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `stock_movements` DROP CHECK `chk_sm_count_ref`');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_count_item_id');
        });
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};

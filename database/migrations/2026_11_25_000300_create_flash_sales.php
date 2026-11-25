<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flash sale (chủ shop duyệt 25/11/2026): nhân viên tạo + gửi duyệt, quản trị viên duyệt. CHỈ THÊM.
 *  - flash_sales: đợt sale (khung giờ, trạng thái duyệt).
 *  - flash_sale_items: giá sale từng cây + số suất (NULL = không giới hạn) + số đã bán (đối soát với dòng hàng).
 *  - order_items.flash_sale_item_id: dòng hàng bán theo suất sale nào (hủy đơn -> trả suất).
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('draft');           // draft | submitted | approved | rejected | cancelled
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
        DbCheck::addMany('flash_sales', [
            'chk_fs_status' => DbCheck::in('status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled']),
            'chk_fs_window' => 'starts_at < ends_at',
            'chk_fs_four_eyes' => 'approved_by IS NULL OR approved_by <> created_by',
        ]);

        Schema::create('flash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('plants');
            $table->unsignedBigInteger('sale_price');
            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->timestamps();

            $table->unique(['flash_sale_id', 'plant_id']);
            $table->index('plant_id');
        });
        DbCheck::addMany('flash_sale_items', [
            'chk_fsi_price' => 'sale_price > 0',
            'chk_fsi_sold' => 'quantity_limit IS NULL OR sold_count <= quantity_limit',
        ]);

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('flash_sale_item_id')->nullable()->after('plant_id')->constrained('flash_sale_items');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flash_sale_item_id');
        });
        Schema::dropIfExists('flash_sale_items');
        DB::statement('DROP TABLE IF EXISTS flash_sales');
    }
};

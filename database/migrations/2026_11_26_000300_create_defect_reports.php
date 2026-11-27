<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu báo hàng lỗi (chủ shop duyệt 26/11/2026): nhân viên lập (quét từng chậu lỗi, chọn loại lỗi, kèm ảnh),
 * quản trị viên duyệt mới xuất hủy khỏi kho. CHỈ THÊM 2 bảng + 1 cột FK trên sổ kho.
 *  - defect_reports: 4 mắt ở tầng DB (người duyệt khác người lập).
 *  - defect_report_items: mỗi dòng = 1 chậu (plant_units), loại lỗi, giá vốn lúc xuất hủy (định giá thiệt hại).
 *  - stock_movements.defect_report_item_id: bút toán "damage" trỏ về dòng phiếu.
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::create('defect_reports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                                  // BL-2611-0001
            $table->string('status', 12)->default('submitted');                   // submitted | approved | rejected
            $table->text('note')->nullable();
            $table->json('photos')->nullable();                                   // tối đa 3 đường dẫn "/storage/defects/…"
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
        DbCheck::addMany('defect_reports', [
            'chk_dr_status' => DbCheck::in('status', ['submitted', 'approved', 'rejected']),
            'chk_dr_decided' => "(status = 'submitted') = (decided_by IS NULL AND decided_at IS NULL)",
            'chk_dr_four_eyes' => 'decided_by IS NULL OR decided_by <> created_by',          // người lập không tự duyệt
        ]);

        Schema::create('defect_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defect_report_id')->constrained('defect_reports');
            $table->foreignId('plant_unit_id')->constrained('plant_units');
            $table->foreignId('plant_id')->constrained('plants');
            $table->string('defect_type', 12);                                    // dead | broken | pest | supplier | shipping | other
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('unit_cost')->nullable();                  // giá vốn lúc xuất hủy (khi duyệt)
            $table->timestamps();

            $table->unique(['defect_report_id', 'plant_unit_id']);
        });
        DbCheck::add('defect_report_items', 'chk_dri_type', DbCheck::in('defect_type', ['dead', 'broken', 'pest', 'supplier', 'shipping', 'other']));

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('defect_report_item_id')->nullable()->after('stock_count_item_id')->constrained('defect_report_items');
        });
        DbCheck::add('stock_movements', 'chk_sm_defect_ref', "defect_report_item_id IS NULL OR type = 'damage'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `stock_movements` DROP CHECK `chk_sm_defect_ref`');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('defect_report_item_id');
        });
        Schema::dropIfExists('defect_report_items');
        Schema::dropIfExists('defect_reports');
    }
};

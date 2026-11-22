<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * K8 (chủ shop duyệt 22/11/2026): thông tin chi tiết của cây cho trang sản phẩm.
 * CHỈ THÊM cột, tất cả cho phép NULL (cây cũ chưa nhập thì trang chỉ ẩn mục đó). Không đụng giá, kho, đơn hàng.
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::table('plants', function (Blueprint $table) {
            $table->string('height', 60)->nullable()->after('description');        // kích thước cây, vd "Cao 40–50 cm"
            $table->string('pot_size', 80)->nullable()->after('height');           // chậu đi kèm, vd "Chậu sứ Ø15 cm"
            $table->string('growth_rate', 10)->nullable()->after('pot_size');      // slow | medium | fast
            $table->string('watering', 100)->nullable()->after('growth_rate');     // vd "2–3 lần/tuần"
            $table->text('growth_info')->nullable()->after('watering');            // quá trình sinh trưởng
            $table->text('care_guide')->nullable()->after('growth_info');          // cách chăm sóc
        });

        DbCheck::add('plants', 'chk_plants_growth_rate', 'growth_rate IS NULL OR '.DbCheck::in('growth_rate', ['slow', 'medium', 'fast']));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `plants` DROP CHECK `chk_plants_growth_rate`');
        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn(['height', 'pot_size', 'growth_rate', 'watering', 'growth_info', 'care_guide']);
        });
    }
};

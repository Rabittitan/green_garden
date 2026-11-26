<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phí ship theo VÙNG + KHỐI LƯỢNG (chủ shop duyệt 25/11/2026; giá do quản trị viên đặt vì hay biến động). CHỈ THÊM.
 *  - shipping_zones: phí cơ bản cho N gram đầu + phí mỗi kg thêm. 1 vùng mặc định (áp cho tỉnh chưa gán vùng).
 *  - shipping_zone_provinces: tỉnh/thành (tên trong danh mục 34 tỉnh) -> vùng; mỗi tỉnh thuộc tối đa 1 vùng.
 *  - plants.weight_grams: khối lượng đóng gói 1 cây (NULL = dùng mặc định trong config/shop.php).
 * Chưa tạo vùng nào -> phí đồng giá cũ (config shop.shipping.base_fee), không đổi hành vi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('base_fee');                    // phí cho base_weight_grams đầu tiên
            $table->unsignedInteger('base_weight_grams')->default(2000);
            $table->unsignedBigInteger('extra_fee_per_kg')->default(0); // mỗi kg (hoặc phần lẻ) vượt mức trên
            $table->boolean('is_default')->default(false);
            // Chỉ 1 vùng mặc định: cột sinh = 1 khi là mặc định, NULL khi không -> UNIQUE
            $table->unsignedTinyInteger('default_key')->nullable()->storedAs('CASE WHEN is_default THEN 1 ELSE NULL END');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('default_key');
        });

        Schema::create('shipping_zone_provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('province', 100)->unique();
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->nullable()->after('pot_size');
        });
        DbCheck::add('plants', 'chk_plants_weight', 'weight_grams IS NULL OR weight_grams BETWEEN 1 AND 200000');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `plants` DROP CHECK `chk_plants_weight`');
        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn('weight_grams');
        });
        Schema::dropIfExists('shipping_zone_provinces');
        Schema::dropIfExists('shipping_zones');
    }
};

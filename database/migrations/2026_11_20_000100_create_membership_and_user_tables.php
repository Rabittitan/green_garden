<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HẠNG THÀNH VIÊN + NGƯỜI DÙNG
 *
 * Hạng CỐ ĐỊNH theo tổng giá trị đã mua (lifetime), không hết hạn, không tự hạ hạng:
 *   users.lifetime_spend = Σ max(0, paid_amount - refunded_amount - (shipping_fee - shipping_discount))
 *                          của các đơn đã "chốt chi tiêu" (orders.spend_recorded_at NOT NULL)
 *   Đơn chỉ được chốt khi đã completed VÀ qua hết thời hạn đổi trả, để không ai mua - lên hạng - trả hàng.
 *   Hạng chỉ đi lên tự động; hạ hạng chỉ khi admin chỉnh tay (có ghi lịch sử).
 *
 * Dữ liệu hạng là dữ liệu tham chiếu nên được chèn ngay trong migration:
 * không thể có user nào trước khi có hạng cơ bản (users.membership_tier_id NOT NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                       // member | silver | gold | diamond
            $table->string('name', 50);
            $table->unsignedTinyInteger('rank')->unique();              // 0 = hạng cơ bản
            $table->unsignedBigInteger('min_spend')->unique();          // tổng chi tiêu để đạt hạng
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedBigInteger('max_discount_per_order')->nullable();
            $table->unsignedBigInteger('free_shipping_min_order')->nullable(); // NULL = dùng ngưỡng chung
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DbCheck::addMany('membership_tiers', [
            'chk_tier_percent' => 'discount_percent <= 50',
            'chk_tier_base' => '`rank` <> 0 OR min_spend = 0',
        ]);

        $now = now();
        DB::table('membership_tiers')->insert([
            ['code' => 'member',  'name' => 'Thành viên', 'rank' => 0, 'min_spend' => 0,        'discount_percent' => 0, 'max_discount_per_order' => null,   'free_shipping_min_order' => null,   'created_at' => $now, 'updated_at' => $now],
            ['code' => 'silver',  'name' => 'Bạc',        'rank' => 1, 'min_spend' => 3000000,  'discount_percent' => 3, 'max_discount_per_order' => 100000, 'free_shipping_min_order' => 400000, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'gold',    'name' => 'Vàng',       'rank' => 2, 'min_spend' => 10000000, 'discount_percent' => 5, 'max_discount_per_order' => 300000, 'free_shipping_min_order' => 300000, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'diamond', 'name' => 'Kim cương',  'rank' => 3, 'min_spend' => 30000000, 'discount_percent' => 8, 'max_discount_per_order' => 800000, 'free_shipping_min_order' => 0,      'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('customer');            // customer | staff | admin
            $table->string('phone', 20)->nullable();
            $table->foreignId('membership_tier_id')->constrained('membership_tiers');
            $table->unsignedBigInteger('lifetime_spend')->default(0);   // cache, dựng lại được từ orders
            $table->timestamp('tier_achieved_at')->nullable();
            $table->boolean('is_active')->default(true);                // khóa tài khoản thay vì xóa
            $table->rememberToken();
            $table->timestamps();
        });

        DbCheck::add('users', 'chk_users_role', DbCheck::in('role', ['customer', 'staff', 'admin']));

        Schema::create('membership_tier_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('from_tier_id')->nullable()->constrained('membership_tiers');
            $table->foreignId('to_tier_id')->constrained('membership_tiers');
            $table->string('reason', 20);                               // initial | upgrade | manual
            $table->unsignedBigInteger('spend_total')->default(0);      // lifetime_spend tại thời điểm đổi
            $table->unsignedBigInteger('order_id')->nullable();         // FK thêm ở migration orders
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        DbCheck::addMany('membership_tier_histories', [
            'chk_mth_reason' => DbCheck::in('reason', ['initial', 'upgrade', 'manual']),
            'chk_mth_manual' => "reason <> 'manual' OR changed_by IS NOT NULL",
        ]);

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');         // RESTRICT: bắt buộc vì cột sinh bên dưới dựa trên user_id
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->string('province', 100);
            $table->string('district', 100);
            $table->string('ward', 100);
            $table->string('address_line');
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('default_key')->nullable()->storedAs('IF(is_default, user_id, NULL)');
            $table->timestamps();

            $table->unique('default_key', 'uq_addr_default');           // mỗi khách tối đa 1 địa chỉ mặc định
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
        Schema::dropIfExists('membership_tier_histories');
        Schema::dropIfExists('users');
        Schema::dropIfExists('membership_tiers');
    }
};

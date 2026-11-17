<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỔ KHO (stock_movements): điểm mù lớn nhất của schema cũ.
 *
 * Trước đây plants.stock chỉ là một con số bị cộng trừ trực tiếp. Khi số tồn bị lệch
 * thì không thể biết lệch từ đâu: bán, hủy đơn, nhập hàng hay cây bị chết.
 *
 * Quy tắc từ nay: MỌI thay đổi plants.stock đều phải ghi đúng 1 dòng vào stock_movements
 * trong CÙNG một transaction. plants.stock chỉ là bản cache của SUM(quantity).
 * Đối soát định kỳ: SELECT plant_id, SUM(quantity) FROM stock_movements GROUP BY plant_id
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();               // NCC-001
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_code', 20)->nullable();         // mã số thuế
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('plants');
            // sale | sale_cancel | purchase_receipt | adjustment | damage (cây chết, hỏng)
            $table->string('type', 30);
            $table->integer('quantity');                         // CÓ DẤU: +nhập / -xuất
            $table->unsignedInteger('stock_after');              // tồn sau giao dịch, giúp đối soát nhanh
            $table->unsignedInteger('unit_cost')->nullable();    // giá vốn tại thời điểm phát sinh
            $table->nullableMorphs('reference');                 // reference_type/id: order, goods_receipt...
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();       // bảng chỉ GHI THÊM, không bao giờ UPDATE

            $table->index(['plant_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        DbCheck::add('stock_movements', 'chk_stock_movements_type',
            DbCheck::in('type', ['sale', 'sale_cancel', 'purchase_receipt', 'adjustment', 'damage']));
        DbCheck::add('stock_movements', 'chk_stock_movements_qty', 'quantity <> 0');

        // Số dư đầu kỳ: biến số tồn hiện tại thành 1 dòng "adjustment" để sổ kho khớp ngay từ đầu
        $now = now();
        DB::table('plants')->where('stock', '>', 0)->eachById(function ($p) use ($now) {
            DB::table('stock_movements')->insert([
                'plant_id' => $p->id, 'type' => 'adjustment', 'quantity' => $p->stock,
                'stock_after' => $p->stock, 'note' => 'Số dư đầu kỳ khi áp dụng sổ kho',
                'created_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('suppliers');
    }
};

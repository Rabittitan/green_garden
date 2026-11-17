<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DANH MỤC + CÂY
 *
 * plants.stock là CACHE của SUM(stock_movements.quantity): chỉ InventoryService được đổi.
 * plants.cost_price là giá vốn bình quân gia quyền, cập nhật mỗi lần nhập kho.
 * rating_sum / rating_count: lưu tổng thay vì trung bình để không sai số làm tròn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories'); // chặn vòng lặp ở service
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('sku', 50)->unique();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('reorder_point')->default(5);
            $table->string('light', 10);
            $table->string('element', 10);
            $table->string('image', 500)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('rating_sum')->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'category_id', 'price']);
            $table->index(['is_active', 'element']);
            $table->index(['is_active', 'light']);
        });

        DbCheck::addMany('plants', [
            'chk_plants_light' => DbCheck::in('light', ['ua_bong', 'ua_sang']),
            'chk_plants_element' => DbCheck::in('element', ['kim', 'moc', 'thuy', 'hoa', 'tho']),
            'chk_plants_price' => 'price > 0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plants');
        Schema::dropIfExists('categories');
    }
};

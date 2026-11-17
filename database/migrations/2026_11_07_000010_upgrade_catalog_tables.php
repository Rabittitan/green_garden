<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Nâng cấp bảng danh mục, cây và bài viết.
 *
 * plants:
 *  - slug, sku: URL thân thiện SEO và mã hàng nội bộ dùng khi nhập kho
 *  - cost_price: giá vốn bình quân, cập nhật mỗi lần nhập kho, dùng để tính lợi nhuận
 *  - reorder_point: ngưỡng tồn tối thiểu của TỪNG cây (thay cho số 5 viết cứng ở Dashboard)
 *  - rating_sum, rating_count: số liệu tổng hợp đánh giá (lưu tổng thay vì trung bình
 *    để không bị sai số làm tròn; trung bình = sum / count)
 *  - is_active, deleted_at: ẩn hoặc ngừng bán mà vẫn giữ lịch sử đơn hàng
 *
 * articles: thêm tác giả, trạng thái xuất bản, slug
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- categories ----
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->foreignId('parent_id')->nullable()->after('id')
                  ->constrained('categories')->nullOnDelete();     // danh mục cha - con
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        // ---- plants ----
        Schema::table('plants', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('sku', 50)->nullable()->unique()->after('slug');
            $table->unsignedInteger('cost_price')->default(0)->after('price');
            $table->unsignedInteger('reorder_point')->default(5)->after('stock');
            $table->unsignedInteger('rating_sum')->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();

            // Index cho các cột lọc ở trang danh sách
            $table->index(['is_active', 'element']);
            $table->index(['is_active', 'light']);
            $table->index(['is_active', 'price']);
        });

        // ---- articles ----
        Schema::table('articles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->foreignId('author_id')->nullable()->after('id')
                  ->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('published');     // draft | published
            $table->timestamp('published_at')->nullable();
            $table->index(['status', 'published_at']);
        });

        // ---- Backfill dữ liệu cũ ----
        foreach (['categories' => 'name', 'plants' => 'name', 'articles' => 'title'] as $tbl => $col) {
            DB::table($tbl)->whereNull('slug')->eachById(function ($row) use ($tbl, $col) {
                DB::table($tbl)->where('id', $row->id)
                    ->update(['slug' => Str::slug($row->{$col}).'-'.$row->id]);
            });
        }
        DB::table('plants')->whereNull('sku')->eachById(function ($row) {
            DB::table('plants')->where('id', $row->id)
                ->update(['sku' => 'GL'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT)]);
        });
        DB::table('articles')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);

        DbCheck::add('plants', 'chk_plants_light', DbCheck::in('light', ['ua_bong', 'ua_sang']));
        DbCheck::add('plants', 'chk_plants_element', DbCheck::in('element', ['kim', 'moc', 'thuy', 'hoa', 'tho']));
        DbCheck::add('articles', 'chk_articles_status', DbCheck::in('status', ['draft', 'published']));
    }

    public function down(): void
    {
        DbCheck::drop('articles', 'chk_articles_status');
        DbCheck::drop('plants', 'chk_plants_element');
        DbCheck::drop('plants', 'chk_plants_light');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropConstrainedForeignId('author_id');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'status', 'published_at']);
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'element']);
            $table->dropIndex(['is_active', 'light']);
            $table->dropIndex(['is_active', 'price']);
            $table->dropUnique(['slug']);
            $table->dropUnique(['sku']);
            $table->dropSoftDeletes();
            $table->dropColumn(['slug', 'sku', 'cost_price', 'reorder_point', 'rating_sum', 'rating_count', 'is_active']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'sort_order']);
        });
    }
};

<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NHẬP HÀNG gồm 2 chứng từ tách biệt:
 *
 *  1) purchase_requests: YÊU CẦU nhập hàng (đề xuất, chờ duyệt), CHƯA làm thay đổi kho.
 *     draft -> submitted -> approved -> ordered -> partially_received -> received
 *                       \-> rejected        \-> cancelled
 *
 *  2) goods_receipts: PHIẾU NHẬP KHO, ghi nhận hàng THỰC TẾ về tới kho.
 *     Chỉ khi phiếu chuyển sang 'completed' mới:
 *       - cộng plants.stock
 *       - ghi stock_movements (type = purchase_receipt)
 *       - cập nhật giá vốn bình quân plants.cost_price
 *       - cộng purchase_request_items.quantity_received
 *
 * Vì sao phải tách? Một yêu cầu 100 cây có thể về làm 2-3 đợt, có cây bị chết hoặc dập
 * khi vận chuyển (quantity_rejected), và giá thực tế có thể khác giá dự kiến.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                       // YCN-2611-0001
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers'); // có thể chọn NCC lúc duyệt
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 30)->default('draft');
            $table->string('priority', 10)->default('normal');          // low | normal | urgent
            $table->text('reason')->nullable();                         // "Sắp Tết, Kim Ngân bán chạy"
            $table->date('needed_by')->nullable();                      // cần hàng trước ngày
            $table->unsignedBigInteger('estimated_total')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('plants');
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->nullable();   // người duyệt có thể giảm số lượng
            $table->unsignedInteger('quantity_received')->default(0);   // cộng dồn từ các phiếu nhập
            $table->unsignedInteger('estimated_unit_cost')->default(0);
            $table->unsignedInteger('stock_at_request')->nullable();    // snapshot tồn lúc đề xuất, giúp duyệt có căn cứ
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'plant_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                       // PN-2611-0001
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests'); // NULL = nhập lẻ không qua yêu cầu
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('status', 20)->default('draft');             // draft | completed | cancelled
            $table->string('supplier_invoice_no', 50)->nullable();      // số hóa đơn của NCC
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->foreignId('received_by')->constrained('users');
            $table->timestamp('received_at')->nullable();               // lúc hoàn tất, kho được cộng
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->nullable()
                  ->constrained('purchase_request_items')->nullOnDelete();
            $table->foreignId('plant_id')->constrained('plants');
            $table->unsignedInteger('quantity_received');               // số cây đạt, được nhập kho
            $table->unsignedInteger('quantity_rejected')->default(0);   // cây chết, dập, không đạt -> trả NCC
            $table->unsignedInteger('unit_cost');                       // giá nhập thực tế
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'plant_id']);
        });

        DbCheck::add('purchase_requests', 'chk_pr_status', DbCheck::in('status',
            ['draft', 'submitted', 'approved', 'rejected', 'ordered', 'partially_received', 'received', 'cancelled']));
        DbCheck::add('purchase_requests', 'chk_pr_priority', DbCheck::in('priority', ['low', 'normal', 'urgent']));
        DbCheck::add('purchase_request_items', 'chk_pri_qty', 'quantity_requested > 0');
        DbCheck::add('goods_receipts', 'chk_gr_status', DbCheck::in('status', ['draft', 'completed', 'cancelled']));
        DbCheck::add('goods_receipt_items', 'chk_gri_qty', 'quantity_received + quantity_rejected > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};

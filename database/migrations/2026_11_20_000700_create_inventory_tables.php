<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KHO + NHẬP HÀNG
 *
 * stock_movements là SỔ KHO chỉ ghi thêm: mọi thay đổi plants.stock đều có đúng 1 dòng ở đây,
 * trong cùng transaction. plants.stock = SUM(quantity) theo plant_id (đối soát hằng ngày).
 * Tham chiếu chứng từ bằng FK THẬT (order_item_id / goods_receipt_item_id) thay vì morph,
 * và DB ép đúng dấu số lượng + đúng loại chứng từ cho từng loại bút toán.
 *
 * Nhập hàng: purchase_requests (đề xuất, duyệt 4 mắt) -> goods_receipts (hàng thực về, cộng kho).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('address')->nullable();
            $table->string('tax_code', 20)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                        // YCN-2611-0001
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 20)->default('draft');
            $table->string('priority', 10)->default('normal');
            $table->text('reason')->nullable();
            $table->date('needed_by')->nullable();
            $table->unsignedBigInteger('estimated_total')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        DbCheck::addMany('purchase_requests', [
            'chk_pr_status' => DbCheck::in('status', ['draft', 'submitted', 'approved', 'rejected', 'ordered', 'partially_received', 'received', 'cancelled']),
            'chk_pr_priority' => DbCheck::in('priority', ['low', 'normal', 'urgent']),
            'chk_pr_4eyes' => 'approved_by IS NULL OR approved_by <> requested_by',
        ]);

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests');
            $table->foreignId('plant_id')->constrained('plants');
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->nullable();
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedBigInteger('estimated_unit_cost')->default(0);
            $table->unsignedInteger('stock_at_request')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'plant_id']);
        });

        DbCheck::addMany('purchase_request_items', [
            'chk_pri_qty' => 'quantity_requested > 0',
            'chk_pri_recv' => 'quantity_received <= COALESCE(quantity_approved, quantity_requested)',
        ]);

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                        // PN-2611-0001
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('status', 20)->default('draft');
            $table->string('supplier_invoice_no', 50)->nullable();
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->foreignId('received_by')->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
        });

        DbCheck::addMany('goods_receipts', [
            'chk_gr_status' => DbCheck::in('status', ['draft', 'completed', 'cancelled']),
            'chk_gr_done' => "(status = 'completed') = (received_at IS NOT NULL)",
        ]);

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts');
            $table->foreignId('purchase_request_item_id')->nullable()->constrained('purchase_request_items');
            $table->foreignId('plant_id')->constrained('plants');
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_rejected')->default(0);
            $table->unsignedBigInteger('unit_cost');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'plant_id']);
        });

        DbCheck::add('goods_receipt_items', 'chk_gri_qty', 'quantity_received + quantity_rejected > 0');

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('plants');
            $table->string('type', 20);
            $table->integer('quantity');                                 // CÓ DẤU: + nhập / - xuất
            $table->unsignedInteger('stock_after');
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items');
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['plant_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        DbCheck::addMany('stock_movements', [
            'chk_sm_type' => DbCheck::in('type', ['opening', 'sale', 'sale_cancel', 'sale_return', 'purchase_receipt', 'adjustment', 'damage']),
            'chk_sm_sign' => "(type IN ('sale','damage') AND quantity < 0)"
                ." OR (type IN ('opening','sale_cancel','sale_return','purchase_receipt') AND quantity > 0)"
                ." OR (type = 'adjustment' AND quantity <> 0)",
            'chk_sm_ref' => "(type IN ('sale','sale_cancel','sale_return') AND order_item_id IS NOT NULL AND goods_receipt_item_id IS NULL)"
                ." OR (type = 'purchase_receipt' AND goods_receipt_item_id IS NOT NULL AND order_item_id IS NULL)"
                ." OR (type IN ('opening','adjustment') AND order_item_id IS NULL AND goods_receipt_item_id IS NULL)"
                ." OR (type = 'damage' AND goods_receipt_item_id IS NULL)",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('suppliers');
    }
};

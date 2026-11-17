<?php

use App\Support\DbCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * THANH TOÁN
 *
 * payments: mỗi lần thu (charge) hoặc hoàn (refund) là 1 dòng.
 *   charge: pending -> succeeded | failed | cancelled ; cancelled -> succeeded CHỈ khi IPN đến trễ
 *   refund: pending -> succeeded | failed ; luôn trỏ về charge gốc (parent_payment_id)
 * DB đảm bảo mỗi đơn có tối đa 1 charge đang pending và tối đa 1 charge thành công
 * (UNIQUE trên cột sinh). Vì cột sinh dựa trên order_id nên FK order_id bắt buộc RESTRICT.
 *
 * payment_webhook_logs: lưu NGUYÊN VĂN mọi callback từ cổng (kể cả sai chữ ký) để tra soát.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('parent_payment_id')->nullable()->constrained('payments');
            $table->string('type', 10);                                  // charge | refund
            $table->string('method', 20);                                // cod | vnpay | bank_transfer
            $table->unsignedBigInteger('amount');
            $table->string('status', 10)->default('pending');
            $table->string('txn_ref', 40)->unique();                     // mã gửi cổng / mã nội bộ
            $table->string('provider_txn_no', 60)->nullable();           // mã giao dịch phía cổng / ngân hàng
            $table->string('provider_response_code', 10)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unsignedBigInteger('pending_charge_key')->nullable()
                ->storedAs("IF(type = 'charge' AND status = 'pending', order_id, NULL)");
            $table->unsignedBigInteger('success_charge_key')->nullable()
                ->storedAs("IF(type = 'charge' AND status = 'succeeded', order_id, NULL)");

            $table->unique('pending_charge_key', 'uq_pay_pending_charge');
            $table->unique('success_charge_key', 'uq_pay_success_charge');
            $table->unique(['method', 'provider_txn_no'], 'uq_pay_provider');
            $table->index(['order_id', 'type', 'status']);
        });

        DbCheck::addMany('payments', [
            'chk_pay_type' => DbCheck::in('type', ['charge', 'refund']),
            'chk_pay_method' => DbCheck::in('method', ['cod', 'vnpay', 'bank_transfer']),
            'chk_pay_status' => DbCheck::in('status', ['pending', 'succeeded', 'failed', 'cancelled']),
            'chk_pay_amount' => 'amount > 0',
            'chk_pay_parent' => "(type = 'refund') = (parent_payment_id IS NOT NULL)",
            'chk_pay_paid_at' => "(status = 'succeeded') = (paid_at IS NOT NULL)",
        ]);

        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('txn_ref', 40)->nullable();
            $table->json('payload');
            $table->boolean('signature_valid');
            $table->string('result_code', 10)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['txn_ref', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('payments');
    }
};

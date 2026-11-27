<?php

use App\Support\DbCheck;
use App\Support\UnitCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mã riêng cho TỪNG chậu cây (chủ shop duyệt 26/11/2026): biết chậu nào nhập ngày nào, từ phiếu nhập nào,
 * giao cho đơn nào, chết / mất lúc nào. CHỈ THÊM 2 bảng mới; không sửa cột cũ.
 *
 * Bất biến (shop:reconcile #10): số chậu "trong kho" của mỗi cây = plants.stock + số đã đặt mà chưa giao
 * (đơn chờ thanh toán / chờ xác nhận / đã xác nhận: tồn đã trừ lúc đặt nhưng chậu vẫn nằm trên kệ tới khi quét giao).
 *
 * Hàng đang có lúc triển khai được phát mã ngay ở đây (sự kiện "created", ghi chú rõ), shop in tem rồi dán.
 */
return new class extends Migration
{
    public function up(): void
    {
        DbCheck::assertSupportedEngine();

        Schema::create('plant_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('plants');
            $table->char('code', 8)->unique();                                   // App\Support\UnitCode
            $table->string('status', 12)->default('in_stock');                    // in_stock | sold | damaged | lost
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items');   // phiếu nhập sinh ra chậu
            $table->foreignId('order_item_id')->nullable()->constrained('order_items');                  // dòng đơn đã giao chậu này
            $table->timestamp('status_changed_at')->nullable();                   // kiểm kê: chậu "nằm yên" từ lúc mở phiếu mới tính mất
            $table->timestamps();

            $table->index(['plant_id', 'status']);
        });
        DbCheck::addMany('plant_units', [
            'chk_pu_status' => DbCheck::in('status', ['in_stock', 'sold', 'damaged', 'lost']),
            'chk_pu_sold' => "status <> 'sold' OR order_item_id IS NOT NULL",                // đã bán thì phải biết bán cho dòng đơn nào
        ]);

        // Lịch sử từng chậu: chỉ ghi thêm (như sổ kho). Bút toán kho gắn kèm cho biết chứng từ gốc (phiếu nhập / hoàn / hủy / kiểm kê).
        Schema::create('plant_unit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_unit_id')->constrained('plant_units');
            $table->string('type', 12);                                           // created | shipped | returned | damaged | lost | found
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['plant_unit_id', 'id']);
        });
        DbCheck::add('plant_unit_events', 'chk_pue_type', DbCheck::in('type', ['created', 'shipped', 'returned', 'damaged', 'lost', 'found']));

        $this->issueForCurrentStock();
    }

    /** Phát mã cho hàng đang trên kệ: tồn hiện tại + phần đã đặt mà chưa giao (chậu vẫn còn trong kho). */
    private function issueForCurrentStock(): void
    {
        $reserved = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
            ->whereIn('o.status', ['pending_payment', 'pending', 'confirmed'])
            ->groupBy('i.plant_id')->selectRaw('i.plant_id, SUM(i.quantity) AS q')->pluck('q', 'plant_id');
        $now = now();
        foreach (DB::table('plants')->orderBy('id')->get(['id', 'stock']) as $plant) {
            $count = (int) $plant->stock + (int) ($reserved[$plant->id] ?? 0);
            for ($done = 0; $done < $count; $done += 500) {
                $batch = min(500, $count - $done);
                $codes = [];
                while (count($codes) < $batch) {
                    $codes[UnitCode::generate()] = true;
                }
                $codes = array_keys($codes);
                DB::table('plant_units')->insert(array_map(fn ($c) => ['plant_id' => $plant->id, 'code' => $c, 'status' => 'in_stock',
                    'status_changed_at' => $now, 'created_at' => $now, 'updated_at' => $now], $codes));
                $ids = DB::table('plant_units')->whereIn('code', $codes)->pluck('id');
                DB::table('plant_unit_events')->insert($ids->map(fn ($id) => ['plant_unit_id' => $id, 'type' => 'created',
                    'note' => 'Phát mã cho hàng tồn khi bắt đầu quản lý từng chậu', 'created_at' => $now])->all());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_unit_events');
        Schema::dropIfExists('plant_units');
    }
};

<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\GoodsReceiptItem;
use App\Models\OrderItem;
use App\Models\Plant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * CỬA DUY NHẤT thay đổi tồn kho.
 *
 * Quy tắc:
 *  - Mỗi thay đổi plants.stock = đúng 1 dòng stock_movements, trong CÙNG transaction.
 *  - Cây phải được KHÓA DÒNG trước khi đổi (lockPlants), theo thứ tự id tăng dần
 *    để các giao dịch song song không deadlock.
 *  - Nhập hàng cập nhật giá vốn bình quân gia quyền.
 */
class InventoryService
{
    /**
     * Khóa các cây theo id tăng dần. Gọi BÊN TRONG transaction.
     *
     * @param  list<int>  $plantIds
     * @return Collection<int, Plant> keyBy id
     */
    public function lockPlants(array $plantIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $plantIds)));
        sort($ids);

        return Plant::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * Ghi 1 bút toán cho cây ĐÃ KHÓA. Gọi bên trong transaction.
     */
    public function move(
        Plant $plant,
        StockMovementType $type,
        int $quantity,
        ?OrderItem $orderItem = null,
        ?GoodsReceiptItem $receiptItem = null,
        ?int $unitCost = null,
        ?string $note = null,
        ?int $by = null,
    ): StockMovement {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('InventoryService::move phải chạy trong transaction có khóa dòng.');
        }
        if (! $type->acceptsQuantity($quantity)) {
            throw new LogicException("Số lượng {$quantity} sai dấu cho bút toán {$type->value}.");
        }
        if ($type->requiresOrderItem() !== ($orderItem !== null) && $type !== StockMovementType::Damage) {
            throw new LogicException("Bút toán {$type->value} ".($type->requiresOrderItem() ? 'bắt buộc' : 'không được').' gắn dòng đơn hàng.');
        }
        if ($type->requiresReceiptItem() !== ($receiptItem !== null)) {
            throw new LogicException("Bút toán {$type->value} ".($type->requiresReceiptItem() ? 'bắt buộc' : 'không được').' gắn dòng phiếu nhập.');
        }

        $newStock = $plant->stock + $quantity;
        if ($newStock < 0) {
            throw new BusinessException("Cây \"{$plant->name}\" không đủ hàng (còn {$plant->stock}).");
        }

        // Giá vốn bình quân gia quyền khi nhập hàng (hoặc tồn đầu kỳ)
        if (in_array($type, [StockMovementType::PurchaseReceipt, StockMovementType::Opening], true) && $unitCost !== null) {
            $plant->cost_price = $newStock > 0
                ? intdiv($plant->stock * $plant->cost_price + $quantity * $unitCost, $newStock)
                : $unitCost;
        }

        $plant->stock = $newStock;
        Plant::writingLedger(fn () => $plant->save());

        return StockMovement::create([
            'plant_id' => $plant->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $newStock,
            'unit_cost' => $unitCost ?? $plant->cost_price,
            'order_item_id' => $orderItem?->id,
            'goods_receipt_item_id' => $receiptItem?->id,
            'created_by' => $by,
            'note' => $note,
        ]);
    }

    /** Tồn đầu kỳ cho cây vừa tạo (admin nhập ở form thêm cây). */
    public function opening(Plant $plant, int $quantity, int $unitCost, ?int $by = null): ?StockMovement
    {
        if ($quantity <= 0) {
            return null;
        }

        return DB::transaction(function () use ($plant, $quantity, $unitCost, $by) {
            $locked = $this->lockPlants([$plant->id])->get($plant->id);
            if ($locked->stockMovements()->exists()) {
                throw new BusinessException('Cây đã có sổ kho, không thể ghi tồn đầu kỳ.');
            }

            return $this->move($locked, StockMovementType::Opening, $quantity, unitCost: $unitCost, note: 'Tồn đầu kỳ', by: $by);
        }, 3);
    }

    /**
     * Kiểm kê: admin nhập SỐ ĐẾM THỰC TẾ, hệ thống tự tính chênh lệch SAU KHI khóa dòng
     * (nên không ghi đè số đã bán trong lúc admin đang mở form). Không chênh lệch -> không ghi.
     */
    public function countTo(int $plantId, int $counted, string $reason, ?int $by = null): ?StockMovement
    {
        if ($counted < 0) {
            throw new BusinessException('Số đếm thực tế không được âm.');
        }

        return DB::transaction(function () use ($plantId, $counted, $reason, $by) {
            $plant = $this->lockPlants([$plantId])->get($plantId) ?? throw new BusinessException('Không tìm thấy cây.');
            $delta = $counted - $plant->stock;

            return $delta === 0 ? null
                : $this->move($plant, StockMovementType::Adjustment, $delta, note: "Kiểm kê: {$reason}", by: $by);
        }, 3);
    }

    /** Ghi hư hỏng / cây chết trong kho. */
    public function recordDamage(int $plantId, int $quantity, string $reason, ?int $by = null): StockMovement
    {
        if ($quantity <= 0) {
            throw new BusinessException('Số lượng hư hỏng phải lớn hơn 0.');
        }

        return DB::transaction(function () use ($plantId, $quantity, $reason, $by) {
            $plant = $this->lockPlants([$plantId])->get($plantId) ?? throw new BusinessException('Không tìm thấy cây.');

            return $this->move($plant, StockMovementType::Damage, -$quantity, note: $reason, by: $by);
        }, 3);
    }
}

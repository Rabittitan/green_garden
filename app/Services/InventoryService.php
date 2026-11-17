<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Plant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CỬA DUY NHẤT để thay đổi tồn kho.
 * Mỗi lần gọi: khóa dòng cây, đổi plants.stock và ghi 1 dòng stock_movements trong cùng transaction.
 */
class InventoryService
{
    public function move(
        int $plantId,
        StockMovementType $type,
        int $quantity,                 // có dấu: + nhập, - xuất
        ?Model $reference = null,
        ?int $unitCost = null,
        ?string $note = null,
    ): StockMovement {
        if ($quantity === 0) {
            throw new RuntimeException('Số lượng thay đổi phải khác 0.');
        }

        return DB::transaction(function () use ($plantId, $type, $quantity, $reference, $unitCost, $note) {
            $plant = Plant::withTrashed()->lockForUpdate()->findOrFail($plantId);
            $newStock = $plant->stock + $quantity;

            if ($newStock < 0) {
                throw new RuntimeException("Cây \"{$plant->name}\" không đủ hàng (còn {$plant->stock}).");
            }

            // Nhập hàng: cập nhật giá vốn bình quân gia quyền
            if ($type === StockMovementType::PurchaseReceipt && $unitCost !== null) {
                $plant->cost_price = $newStock > 0
                    ? intdiv($plant->stock * $plant->cost_price + $quantity * $unitCost, $newStock)
                    : $unitCost;
            }

            $plant->stock = $newStock;
            Plant::$ledgerHandled = true;
            try {
                $plant->save();
            } finally {
                Plant::$ledgerHandled = false;
            }

            return StockMovement::create([
                'plant_id' => $plant->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_after' => $newStock,
                'unit_cost' => $unitCost ?? ($plant->cost_price ?: null),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_by' => auth()->id(),
                'note' => $note,
            ]);
        });
    }
}

<?php

namespace App\Services;

use App\Enums\PlantUnitStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\DefectReportItem;
use App\Models\GoodsReceiptItem;
use App\Models\OrderItem;
use App\Models\Plant;
use App\Models\PlantUnit;
use App\Models\StockCountItem;
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
    public function __construct(private PlantUnitService $units) {}

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
     *
     * Đi kèm từng chậu (plant_units), để chậu "trong kho" luôn = tồn + số đã đặt chưa giao:
     *  - Tồn đầu kỳ / nhập kho (+n): phát n chậu mới, mỗi chậu một mã.
     *  - Hoàn hàng (+n): n chậu đã giao của dòng đơn quay về kho.
     *  - Xuất hủy (−n): $units = đúng n chậu trong kho bị hủy.  Kiểm kê: −n = n chậu thất lạc, +n = n chậu thất lạc tìm thấy lại.
     *  - Bán / hủy đơn trước khi giao: không đụng chậu (chậu vẫn trên kệ; chỉ quét lúc giao).
     *
     * @param  Collection<int, PlantUnit>|null  $units  chậu ĐÃ KHÓA (xuất hủy / kiểm kê)
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
        ?StockCountItem $countItem = null,
        ?Collection $units = null,
        ?DefectReportItem $defectItem = null,
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
        if ($countItem !== null && $type !== StockMovementType::Adjustment) {
            throw new LogicException('Chỉ bút toán kiểm kê mới gắn dòng phiếu kiểm kê.');
        }
        if ($defectItem !== null && $type !== StockMovementType::Damage) {
            throw new LogicException('Chỉ bút toán xuất hủy mới gắn dòng phiếu báo lỗi.');
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

        $needsUnits = in_array($type, [StockMovementType::Damage, StockMovementType::Adjustment], true);
        if ($needsUnits !== ($units !== null) || ($units !== null && $units->count() !== abs($quantity))) {
            throw new LogicException("Bút toán {$type->value} ".($needsUnits ? 'phải kèm đúng '.abs($quantity).' chậu' : 'không kèm chậu').'.');
        }

        $plant->stock = $newStock;
        Plant::writingLedger(fn () => $plant->save());

        $movement = StockMovement::create([
            'plant_id' => $plant->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $newStock,
            'unit_cost' => $unitCost ?? $plant->cost_price,
            'order_item_id' => $orderItem?->id,
            'goods_receipt_item_id' => $receiptItem?->id,
            'stock_count_item_id' => $countItem?->id,
            'defect_report_item_id' => $defectItem?->id,
            'created_by' => $by,
            'note' => $note,
        ]);

        switch ($type) {
            case StockMovementType::Opening:
            case StockMovementType::PurchaseReceipt:
                PlantUnitService::insert($plant->id, $quantity, $movement->id, $receiptItem?->id, $by, $note ?? $type->value);
                break;
            case StockMovementType::SaleReturn:
                $this->units->returnItem($orderItem ?? throw new LogicException('Thiếu dòng đơn.'), $quantity, $movement, $by);
                break;
            case StockMovementType::Damage:
                $this->units->change($units ?? new Collection, PlantUnitStatus::InStock, PlantUnitStatus::Damaged, 'damaged', $movement, $by, $note);
                break;
            case StockMovementType::Adjustment:
                [$from, $to, $event] = $quantity < 0 ? [PlantUnitStatus::InStock, PlantUnitStatus::Lost, 'lost'] : [PlantUnitStatus::Lost, PlantUnitStatus::InStock, 'found'];
                $this->units->change($units ?? new Collection, $from, $to, $event, $movement, $by, $note);
                break;
            default:                                                          // bán / hủy trước khi giao: chậu vẫn trên kệ
        }

        return $movement;
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
}

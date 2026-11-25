<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Plant;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phiếu kiểm kê (chỉ quản trị viên).
 *  1. Lập phiếu: chụp lại TỒN TRÊN SỔ + giá vốn của các cây đang bán (tất cả hoặc 1 danh mục).
 *  2. Nhập số đếm (lưu nhiều lần) -> báo cáo chênh lệch: số lượng và giá trị (theo giá vốn lúc lập phiếu).
 *  3. Chốt phiếu: mỗi dòng chênh lệch ghi 1 bút toán "adjustment" = (đếm − sổ lúc lập phiếu), trỏ về dòng phiếu.
 *     Áp CHÊNH LỆCH (không ghi đè bằng số đếm) nên hàng bán / nhập trong lúc đang đếm vẫn được tính đúng.
 *  Khóa: stock_counts -> plants (id tăng dần), như các luồng chứng từ khác (ADR-003).
 */
class StockCountService
{
    public function __construct(private InventoryService $inventory, private PurchaseService $purchases) {}

    public function create(User $admin, ?int $categoryId, ?string $note): StockCount
    {
        $this->assertAdmin($admin);
        if (StockCount::where('status', 'draft')->exists()) {
            throw new BusinessException('Đang có phiếu kiểm kê chưa chốt. Hãy chốt hoặc hủy phiếu đó trước.');
        }

        return DB::transaction(function () use ($admin, $categoryId, $note) {
            $count = $this->purchases->createWithCode('KK', 'stock_counts', fn (string $code) => tap(new StockCount(['code' => $code, 'category_id' => $categoryId, 'note' => $note]),
                fn (StockCount $c) => $c->forceFill(['created_by' => $admin->id, 'status' => 'draft'])->save()));
            $plants = Plant::active()->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))->orderBy('name')->get();
            if ($plants->isEmpty()) {
                throw new BusinessException('Không có cây nào để kiểm kê.');
            }
            foreach ($plants as $p) {
                $count->items()->create(['plant_id' => $p->id, 'system_qty' => $p->stock, 'unit_cost' => $p->cost_price]);
            }

            return $count;
        }, 3);
    }

    /** @param array<int, array{counted_qty: int|null, note?: string|null}> $lines  item_id => ... */
    public function saveCounts(StockCount $count, User $admin, array $lines): StockCount
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($count, $lines) {
            $count = $this->lockDraft($count);
            $items = $count->items()->get()->keyBy('id');
            foreach ($lines as $itemId => $line) {
                $item = $items->get((int) $itemId);
                if (! $item) {
                    continue;
                }
                $qty = $line['counted_qty'];
                if ($qty !== null && $qty < 0) {
                    throw new BusinessException('Số đếm không được âm.');
                }
                $item->update(['counted_qty' => $qty, 'note' => $line['note'] ?? $item->note]);
            }

            return $count;
        }, 3);
    }

    public function complete(StockCount $count, User $admin): StockCount
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($count, $admin) {
            $count = $this->lockDraft($count);
            $items = $count->items()->get();
            if ($items->contains(fn ($i) => $i->counted_qty === null)) {
                throw new BusinessException('Còn cây chưa nhập số đếm. Nhập đủ (cây không đếm được thì ghi bằng số trên sổ) rồi mới chốt.');
            }
            $diff = $items->filter(fn ($i) => $i->variance() !== 0);
            $plants = $this->inventory->lockPlants($diff->pluck('plant_id')->all());
            foreach ($diff->sortBy('plant_id') as $item) {
                $this->inventory->move($plants[$item->plant_id], StockMovementType::Adjustment, (int) $item->variance(),
                    unitCost: $item->unit_cost, note: "Kiểm kê {$count->code}", by: $admin->id, countItem: $item);
            }
            $count->forceFill(['status' => 'completed', 'completed_by' => $admin->id, 'completed_at' => now()])->save();

            return $count;
        }, 3);
    }

    public function cancel(StockCount $count, User $admin): StockCount
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($count) {
            $count = $this->lockDraft($count);
            $count->forceFill(['status' => 'cancelled'])->save();

            return $count;
        }, 3);
    }

    private function lockDraft(StockCount $count): StockCount
    {
        $locked = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== 'draft') {
            throw new BusinessException("Phiếu {$locked->code} đã {$locked->statusLabel()}, không sửa được nữa.");
        }

        return $locked;
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new BusinessException('Kiểm kê là việc của quản trị viên.');
        }
    }
}

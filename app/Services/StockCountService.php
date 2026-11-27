<?php

namespace App\Services;

use App\Enums\PlantUnitStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Plant;
use App\Models\PlantUnit;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\User;
use App\Support\UnitCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phiếu kiểm kê (chỉ quản trị viên), đếm bằng QUÉT MÃ TỪNG CHẬU.
 *  1. Lập phiếu: chụp lại TỒN TRÊN SỔ + giá vốn của các cây đang bán (tất cả hoặc 1 danh mục); mốc giờ mở phiếu.
 *  2. Quét tem từng chậu trên kệ (mỗi chậu tính 1 lần). Xem trước: chậu còn thiếu (chưa quét) và chậu tìm thấy lại.
 *  3. Chốt phiếu, mỗi cây:
 *     - chậu "trong kho" NẰM YÊN từ lúc mở phiếu mà không được quét -> thất lạc: bút toán "adjustment" −n;
 *     - chậu đang "thất lạc" mà được quét -> về kho: bút toán "adjustment" +n.
 *     Chậu vừa nhập / hoàn về / giao trong lúc đang đếm không bị tính oan. Áp CHÊNH LỆCH nên hàng bán trong lúc đếm vẫn đúng.
 *  Khóa: stock_counts -> plants (id tăng dần) -> plant_units (id tăng dần), như các luồng chứng từ khác (ADR-003).
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
            $mark = (int) DB::table('plant_unit_events')->max('id');           // chậu có sự kiện sau mốc này = không nằm yên
            $count = $this->purchases->createWithCode('KK', 'stock_counts', fn (string $code) => tap(new StockCount(['code' => $code, 'category_id' => $categoryId, 'note' => $note]),
                fn (StockCount $c) => $c->forceFill(['created_by' => $admin->id, 'status' => 'draft', 'unit_event_mark' => $mark])->save()));
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

    /**
     * Ghi chú từng dòng (VD: "2 chậu để ở kho sau").
     *
     * @param  array<int, string|null>  $notes  item_id => ghi chú
     */
    public function saveNotes(StockCount $count, User $admin, array $notes): StockCount
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($count, $notes) {
            $count = $this->lockDraft($count);
            foreach ($count->items()->whereIn('id', array_keys($notes))->get() as $item) {
                $item->update(['note' => $notes[$item->id] ?? null]);
            }

            return $count;
        }, 3);
    }

    /**
     * Quét 1 chậu vào phiếu. Trả về chậu + dòng phiếu của cây đó.
     *
     * @return array{unit: PlantUnit, item: StockCountItem}
     */
    public function scan(StockCount $count, User $admin, string $raw): array
    {
        $this->assertAdmin($admin);
        $code = UnitCode::normalize($raw) ?? throw new BusinessException("\"{$raw}\" không phải mã chậu hợp lệ.");

        return DB::transaction(function () use ($count, $admin, $code) {
            $count = $this->lockDraft($count);
            $unit = PlantUnit::with('plant')->firstWhere('code', $code) ?? throw new BusinessException('Không có chậu nào mang mã '.UnitCode::format($code).'.');
            $item = $count->items()->where('plant_id', $unit->plant_id)->first()
                ?? throw new BusinessException("Cây \"{$unit->plant->name}\" không thuộc phạm vi phiếu này.");
            if (! in_array($unit->status, [PlantUnitStatus::InStock, PlantUnitStatus::Lost], true)) {
                throw new BusinessException('Chậu '.$unit->displayCode().' đang "'.$unit->status->label().'": không thể nằm trên kệ. Kiểm tra lại chậu này.');
            }
            if (DB::table('stock_count_scans')->where('stock_count_id', $count->id)->where('plant_unit_id', $unit->id)->exists()) {
                throw new BusinessException('Chậu '.$unit->displayCode().' đã quét rồi.');
            }
            DB::table('stock_count_scans')->insert(['stock_count_id' => $count->id, 'plant_unit_id' => $unit->id,
                'created_by' => $admin->id, 'created_at' => now()]);

            return ['unit' => $unit, 'item' => $item];
        }, 3);
    }

    /** Bỏ 1 chậu đã quét nhầm. */
    public function unscan(StockCount $count, User $admin, string $raw): void
    {
        $this->assertAdmin($admin);
        $code = UnitCode::normalize($raw) ?? throw new BusinessException("\"{$raw}\" không phải mã chậu hợp lệ.");
        DB::transaction(function () use ($count, $code) {
            $count = $this->lockDraft($count);
            DB::table('stock_count_scans')->where('stock_count_id', $count->id)
                ->whereIn('plant_unit_id', PlantUnit::where('code', $code)->select('id'))->delete();
        }, 3);
    }

    /**
     * Xem trước kết quả (cũng là căn cứ khi chốt): mỗi dòng phiếu -> số chậu cần thấy, đã quét, mã chậu thiếu, mã chậu tìm lại.
     *
     * @return array<int, array{expected: int, scanned: int, missing: list<string>, found: list<string>}> item_id => ...
     */
    public function preview(StockCount $count): array
    {
        $items = $count->items()->get(['id', 'plant_id']);
        $scanned = PlantUnit::whereIn('id', DB::table('stock_count_scans')->where('stock_count_id', $count->id)->select('plant_unit_id'))
            ->get(['id', 'plant_id', 'code', 'status'])->groupBy('plant_id');
        $expected = $this->steadyOnShelf($count, $items->pluck('plant_id')->all())->groupBy('plant_id');
        $out = [];
        foreach ($items as $item) {
            $seen = $scanned->get($item->plant_id, collect());
            $out[$item->id] = [
                'expected' => $expected->get($item->plant_id, collect())->count(),
                'scanned' => $seen->count(),
                'missing' => $expected->get($item->plant_id, collect())->pluck('code')->diff($seen->pluck('code'))->values()->all(),
                'found' => $seen->where('status', PlantUnitStatus::Lost)->pluck('code')->values()->all(),
            ];
        }

        return $out;
    }

    public function complete(StockCount $count, User $admin): StockCount
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($count, $admin) {
            $count = $this->lockDraft($count);
            $items = $count->items()->get()->keyBy('plant_id');
            $preview = $this->preview($count);
            $codes = collect($preview)->flatMap(fn ($p) => [...$p['missing'], ...$p['found']])->all();
            $changing = PlantUnit::whereIn('code', $codes)->pluck('plant_id')->unique()->all();
            $plants = $this->inventory->lockPlants($changing);
            $units = PlantUnit::whereIn('code', $codes)->orderBy('id')->lockForUpdate()->get();
            $steady = $this->steadyOnShelf($count, $changing)->modelKeys();     // đọc lại sau khi khóa
            foreach ($items as $plantId => $item) {
                $p = $preview[$item->id];
                // Đọc lại sau khi khóa: chậu đổi trạng thái giữa chừng thì bỏ qua (không tính oan)
                $missing = $units->filter(fn (PlantUnit $u) => in_array($u->code, $p['missing'], true) && $u->status === PlantUnitStatus::InStock
                    && in_array($u->id, $steady, true));
                $found = $units->filter(fn (PlantUnit $u) => in_array($u->code, $p['found'], true) && $u->status === PlantUnitStatus::Lost);
                if ($missing->isNotEmpty()) {
                    $this->inventory->move($plants[$plantId], StockMovementType::Adjustment, -$missing->count(), unitCost: $item->unit_cost,
                        note: "Kiểm kê {$count->code}: không thấy trên kệ", by: $admin->id, countItem: $item, units: new Collection($missing->values()->all()));
                }
                if ($found->isNotEmpty()) {
                    $this->inventory->move($plants[$plantId], StockMovementType::Adjustment, $found->count(), unitCost: $item->unit_cost,
                        note: "Kiểm kê {$count->code}: tìm thấy lại", by: $admin->id, countItem: $item, units: new Collection($found->values()->all()));
                }
                $item->update(['counted_qty' => $item->system_qty + $found->count() - $missing->count()]);
            }
            $count->forceFill(['status' => 'completed', 'completed_by' => $admin->id, 'completed_at' => now()])->save();

            return $count;
        }, 3);
    }

    /**
     * Chậu "trong kho" nằm yên từ lúc mở phiếu (không có sự kiện nào sau mốc unit_event_mark): những chậu phải thấy khi quét.
     *
     * @param  list<int>  $plantIds
     * @return Collection<int, PlantUnit>
     */
    private function steadyOnShelf(StockCount $count, array $plantIds): Collection
    {
        return PlantUnit::whereIn('plant_id', $plantIds)->where('status', PlantUnitStatus::InStock->value)
            ->when($count->unit_event_mark !== null,
                fn ($q) => $q->whereNotExists(fn ($e) => $e->from('plant_unit_events')->whereColumn('plant_unit_events.plant_unit_id', 'plant_units.id')
                    ->where('plant_unit_events.id', '>', $count->unit_event_mark)),
                fn ($q) => $q->where('status_changed_at', '<', $count->created_at))      // phiếu mở trước khi có cột mốc
            ->get(['id', 'plant_id', 'code']);
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

<?php

namespace App\Services;

use App\Enums\PlantUnitStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\DefectReport;
use App\Models\DefectReportItem;
use App\Models\PlantUnit;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\UnitCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phiếu báo hàng lỗi (thay cho "ghi hư hỏng" bấm là trừ kho ngay).
 *
 *  - Nhân viên (nhóm Kho) lập phiếu: quét từng chậu lỗi đang trong kho, chọn loại lỗi, ghi chú, tối đa 3 ảnh.
 *  - Quản trị viên KHÁC người lập duyệt -> mỗi chậu 1 bút toán "damage" −1 gắn dòng phiếu, chậu thành "đã hủy (lỗi)".
 *    Từ chối -> không đổi kho.
 *
 * THỨ TỰ KHÓA khi duyệt: defect_reports -> plants (id tăng dần) -> plant_units (id tăng dần).
 */
class DefectReportService
{
    public const PHOTO_DIR = 'defects';

    public const MAX_PHOTOS = 3;

    public function __construct(
        private InventoryService $inventory,
        private PurchaseService $purchases,
        private ImageStorage $images,
    ) {}

    /**
     * @param  array<string, array{type?: string, note?: string|null}>  $lines  mã chậu (quét / gõ) => loại lỗi + ghi chú
     * @param  list<UploadedFile>  $photos
     */
    public function create(User $by, array $lines, ?string $note, array $photos = []): DefectReport
    {
        if ($lines === []) {
            throw new BusinessException('Phiếu báo lỗi cần ít nhất 1 chậu: quét tem của chậu lỗi.');
        }
        if (count($photos) > self::MAX_PHOTOS) {
            throw new BusinessException('Tối đa '.self::MAX_PHOTOS.' ảnh mỗi phiếu.');
        }
        $byCode = [];
        foreach ($lines as $raw => $line) {
            $code = UnitCode::normalize((string) $raw) ?? throw new BusinessException("\"{$raw}\" không phải mã chậu hợp lệ.");
            if (! array_key_exists($line['type'] ?? '', DefectReportItem::TYPES)) {
                throw new BusinessException('Chọn loại lỗi cho chậu '.UnitCode::format($code).'.');
            }
            $byCode[$code] = $line;
        }
        $units = PlantUnit::with('plant')->whereIn('code', array_keys($byCode))->get()->keyBy('code');
        foreach (array_keys($byCode) as $code) {
            $u = $units->get($code) ?? throw new BusinessException('Không có chậu nào mang mã '.UnitCode::format($code).'.');
            if ($u->status !== PlantUnitStatus::InStock) {
                throw new BusinessException('Chậu '.$u->displayCode().' đang "'.$u->status->label().'", chỉ báo lỗi chậu đang trong kho.');
            }
        }
        $pending = DefectReportItem::whereIn('plant_unit_id', $units->modelKeys())
            ->whereHas('report', fn ($q) => $q->where('status', 'submitted'))->with(['unit', 'report'])->first();
        if ($pending) {
            throw new BusinessException('Chậu '.$pending->unit->displayCode()." đã nằm trong phiếu {$pending->report->code} đang chờ duyệt.");
        }

        $stored = [];
        try {
            foreach ($photos as $photo) {
                $stored[] = $this->images->store($photo, self::PHOTO_DIR);
            }

            return DB::transaction(fn () => $this->purchases->createWithCode('BL', 'defect_reports', function (string $code) use ($by, $note, $stored, $units, $byCode) {
                $report = new DefectReport(['code' => $code, 'note' => $note, 'photos' => $stored ?: null]);
                $report->created_by = $by->id;
                $report->status = 'submitted';
                $report->save();
                foreach ($byCode as $unitCode => $line) {
                    $u = $units[$unitCode];
                    $report->items()->create(['plant_unit_id' => $u->id, 'plant_id' => $u->plant_id,
                        'defect_type' => $line['type'], 'note' => $line['note'] ?? null]);
                }

                return $report;
            }), 3);
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                $this->images->delete($path, self::PHOTO_DIR);
            }

            throw $e;
        }
    }

    public function approve(DefectReport $report, User $admin): DefectReport
    {
        if (! $admin->isAdmin()) {
            throw new BusinessException('Chỉ quản trị viên duyệt phiếu báo lỗi.');
        }

        return DB::transaction(function () use ($report, $admin) {
            $r = $this->lockSubmitted($report);
            if ($r->created_by === $admin->id) {
                throw new BusinessException('Người lập phiếu không được tự duyệt.');
            }
            $items = $r->items()->get();
            $plants = $this->inventory->lockPlants($items->pluck('plant_id')->all());
            $units = PlantUnit::whereIn('id', $items->pluck('plant_unit_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items->sortBy('plant_id') as $item) {
                /** @var PlantUnit $unit */
                $unit = $units[$item->plant_unit_id];
                if ($unit->status !== PlantUnitStatus::InStock) {
                    throw new BusinessException('Chậu '.$unit->displayCode().' đã "'.$unit->status->label().'" sau khi lập phiếu. Từ chối phiếu này rồi lập lại.');
                }
                $plant = $plants[$item->plant_id];
                if ($plant->stock < 1) {
                    throw new BusinessException("Mọi chậu \"{$plant->name}\" trên kệ đều đã có đơn đặt. Hủy / đổi đơn đó trước khi xuất hủy chậu lỗi.");
                }
                $movement = $this->inventory->move($plant, StockMovementType::Damage, -1,
                    note: "Phiếu báo lỗi {$r->code}: ".$item->typeLabel(), by: $admin->id, units: new Collection([$unit]), defectItem: $item);
                $item->forceFill(['unit_cost' => $movement->unit_cost])->save();
            }
            $r->forceFill(['status' => 'approved', 'decided_by' => $admin->id, 'decided_at' => now()])->save();

            return $r;
        }, 3);
    }

    public function reject(DefectReport $report, User $admin, string $reason): DefectReport
    {
        if (! $admin->isAdmin()) {
            throw new BusinessException('Chỉ quản trị viên từ chối phiếu báo lỗi.');
        }

        return DB::transaction(function () use ($report, $admin, $reason) {
            $r = $this->lockSubmitted($report);
            if ($r->created_by === $admin->id) {
                throw new BusinessException('Người lập phiếu không được tự xử lý phiếu của mình.');
            }
            $r->forceFill(['status' => 'rejected', 'decided_by' => $admin->id, 'decided_at' => now(), 'reject_reason' => $reason])->save();

            return $r;
        }, 3);
    }

    /**
     * Số liệu "Báo cáo hàng lỗi" trong kỳ: mọi bút toán xuất hủy (phiếu báo lỗi, hàng hoàn bị hỏng, ghi hư hỏng cũ),
     * theo loại lỗi / cây / nhà cung cấp (theo phiếu nhập của từng chậu), kèm giá trị thiệt hại theo giá vốn.
     *
     * @return array{qty: int, loss: int, byType: array<string, array{label: string, qty: int, loss: int}>,
     *     byPlant: list<array{name: string, qty: int, loss: int}>, bySupplier: list<array{name: string, qty: int, loss: int}>}
     */
    public function summary(Carbon $fromUtc, Carbon $toUtc): array
    {
        $movements = StockMovement::where('type', StockMovementType::Damage->value)
            ->where('created_at', '>=', $fromUtc)->where('created_at', '<', $toUtc)
            ->with(['plant', 'defectItem'])->get();
        $suppliers = DB::table('plant_unit_events as e')
            ->join('plant_units as u', 'u.id', '=', 'e.plant_unit_id')
            ->leftJoin('goods_receipt_items as gi', 'gi.id', '=', 'u.goods_receipt_item_id')
            ->leftJoin('goods_receipts as g', 'g.id', '=', 'gi.goods_receipt_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'g.supplier_id')
            ->where('e.type', 'damaged')->whereIn('e.stock_movement_id', $movements->modelKeys())
            ->get(['e.stock_movement_id', 's.name']);

        $out = ['qty' => 0, 'loss' => 0, 'byType' => [], 'byPlant' => [], 'bySupplier' => []];
        $add = function (array &$bucket, string $key, string $label, int $qty, int $loss) {
            $bucket[$key] ??= ['name' => $label, 'label' => $label, 'qty' => 0, 'loss' => 0];
            $bucket[$key]['qty'] += $qty;
            $bucket[$key]['loss'] += $loss;
        };
        $byPlant = [];
        $bySupplier = [];
        foreach ($movements as $m) {
            $qty = -$m->quantity;
            $loss = $qty * (int) $m->unit_cost;
            [$type, $label] = match (true) {
                $m->defectItem !== null => [$m->defectItem->defect_type, $m->defectItem->typeLabel()],
                $m->order_item_id !== null => ['return', 'Hàng hoàn bị hỏng'],
                default => ['legacy', 'Ghi hư hỏng (trước khi có phiếu)'],
            };
            $out['qty'] += $qty;
            $out['loss'] += $loss;
            $add($out['byType'], $type, $label, $qty, $loss);
            $add($byPlant, (string) $m->plant_id, $m->plant->name, $qty, $loss);
            $names = $suppliers->where('stock_movement_id', $m->id)->pluck('name')->all();
            foreach ($names === [] ? array_fill(0, $qty, null) : $names as $name) {                   // mỗi chậu tính 1 lần
                $add($bySupplier, (string) ($name ?? ''), $name ?? 'Không rõ (tồn cũ / tồn đầu kỳ)', 1, (int) $m->unit_cost);
            }
        }
        $byLoss = fn (array $a, array $b) => $b['loss'] <=> $a['loss'];      // thiệt hại lớn trước
        uasort($out['byType'], $byLoss);
        $out['byType'] = array_map(fn ($r) => ['label' => $r['label'], 'qty' => $r['qty'], 'loss' => $r['loss']], $out['byType']);
        foreach (['byPlant' => $byPlant, 'bySupplier' => $bySupplier] as $key => $rows) {
            usort($rows, $byLoss);
            $out[$key] = array_map(fn ($r) => ['name' => $r['name'], 'qty' => $r['qty'], 'loss' => $r['loss']], $rows);
        }

        return $out;
    }

    private function lockSubmitted(DefectReport $report): DefectReport
    {
        $r = DefectReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
        if ($r->status !== 'submitted') {
            throw new BusinessException("Phiếu {$r->code} đã được xử lý ({$r->statusLabel()}).");
        }

        return $r;
    }
}

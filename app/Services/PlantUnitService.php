<?php

namespace App\Services;

use App\Enums\PlantUnitStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantUnit;
use App\Models\StockMovement;
use App\Support\UnitCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Từng chậu cây có mã riêng (plant_units). Mọi thay đổi chạy BÊN TRONG transaction của nghiệp vụ gọi tới,
 * cùng lúc với sổ kho, nên luôn giữ bất biến: chậu "trong kho" = plants.stock + số đã đặt mà chưa giao.
 *
 *  - Tạo chậu: InventoryService::move (tồn đầu kỳ, nhập kho, hàng hoàn chưa có tem).
 *  - Giao hàng: shipOrder() gán đúng chậu đã quét cho từng dòng đơn (không đổi sổ kho: tồn đã trừ lúc đặt).
 *  - Hoàn hàng / xuất hủy / kiểm kê: InventoryService::move gọi returnItem() / change().
 *
 * Thứ tự khóa: plant_units luôn khóa SAU orders và plants (docs/adr/003), theo id tăng dần.
 */
class PlantUnitService
{
    /**
     * Tạo $count chậu mới "trong kho", mỗi chậu 1 sự kiện "created". Dùng cả cho seeder (không cần model).
     *
     * @return list<int> id các chậu vừa tạo
     */
    public static function insert(int $plantId, int $count, ?int $movementId, ?int $receiptItemId, ?int $by, string $note, ?Carbon $now = null): array
    {
        $now ??= now();
        $ids = [];
        for ($done = 0; $done < $count; $done += 500) {
            $batch = min(500, $count - $done);
            for ($attempt = 1; ; $attempt++) {
                $codes = [];
                while (count($codes) < $batch) {
                    $codes[UnitCode::generate()] = true;
                }
                $codes = array_keys($codes);
                try {
                    DB::table('plant_units')->insert(array_map(fn ($c) => ['plant_id' => $plantId, 'code' => $c, 'status' => PlantUnitStatus::InStock->value,
                        'goods_receipt_item_id' => $receiptItemId, 'status_changed_at' => $now, 'created_at' => $now, 'updated_at' => $now], $codes));
                    break;
                } catch (UniqueConstraintViolationException $e) {          // trùng mã (gần như không xảy ra): sinh lại cả lô
                    if ($attempt >= 3) {
                        throw $e;
                    }
                }
            }
            $batchIds = DB::table('plant_units')->whereIn('code', $codes)->orderBy('id')->pluck('id')->all();
            DB::table('plant_unit_events')->insert(array_map(fn ($id) => ['plant_unit_id' => $id, 'type' => 'created',
                'stock_movement_id' => $movementId, 'note' => $note, 'created_by' => $by, 'created_at' => $now], $batchIds));
            array_push($ids, ...$batchIds);
        }

        return $ids;
    }

    /**
     * Khóa các chậu theo mã quét / gõ (id tăng dần). Mã sai định dạng, trùng hoặc không tồn tại -> báo lỗi rõ mã nào.
     *
     * @param  list<string>  $inputs
     * @return Collection<int, PlantUnit> keyBy code
     */
    public function lockByCodes(array $inputs): Collection
    {
        $codes = [];
        foreach ($inputs as $raw) {
            $code = UnitCode::normalize((string) $raw) ?? throw new BusinessException("\"{$raw}\" không phải mã chậu hợp lệ.");
            if (isset($codes[$code])) {
                throw new BusinessException('Mã '.UnitCode::format($code).' bị quét 2 lần.');
            }
            $codes[$code] = true;
        }
        $units = PlantUnit::whereIn('code', array_keys($codes))->orderBy('id')->lockForUpdate()->get()->keyBy('code');
        $missing = array_diff(array_keys($codes), $units->keys()->all());
        if ($missing !== []) {
            throw new BusinessException('Không có chậu nào mang mã '.implode(', ', array_map([UnitCode::class, 'format'], $missing)).'.');
        }

        return $units;
    }

    /**
     * Giao hàng: gán chậu cho từng dòng đơn (đơn đã khóa). $codes = mã nhân viên quét khi đóng gói, phải ĐỦ và ĐÚNG cây.
     * $codes = null: tự lấy chậu nhập trước giao trước (chỉ dùng cho lệnh nội bộ / test; màn hình giao hàng luôn bắt quét).
     *
     * @param  list<string>|null  $codes
     */
    public function shipOrder(Order $order, ?array $codes, ?int $by): void
    {
        $items = $order->items()->orderBy('id')->get();
        if ($codes === null) {
            foreach ($items->sortBy('plant_id') as $item) {                 // chậu vừa gán đã thành "đã giao": lần lặp sau không lấy lại
                $found = PlantUnit::where('plant_id', $item->plant_id)->where('status', PlantUnitStatus::InStock->value)
                    ->orderBy('id')->limit($item->quantity)->lockForUpdate()->get();
                if ($found->count() < $item->quantity) {
                    throw new BusinessException("Không đủ chậu \"{$item->plant_name}\" trong kho để giao.");
                }
                $this->markShipped($found, $item, $by);
            }

            return;
        }

        $units = $this->lockByCodes($codes);
        foreach ($units as $u) {
            if ($u->status !== PlantUnitStatus::InStock) {
                throw new BusinessException('Chậu '.$u->displayCode().' đang ở trạng thái "'.$u->status->label().'", không giao được.');
            }
        }
        $pool = $units->groupBy('plant_id');
        foreach ($items as $item) {
            /** @var \Illuminate\Support\Collection<int, PlantUnit> $mine */
            $mine = $pool->get($item->plant_id, collect());
            if ($mine->count() < $item->quantity) {
                throw new BusinessException("Dòng \"{$item->plant_name}\" cần {$item->quantity} chậu, mới quét {$mine->count()}.");
            }
            $this->markShipped(new Collection($mine->take($item->quantity)->all()), $item, $by);
            $pool->put($item->plant_id, $mine->slice($item->quantity));
        }
        $extra = $pool->flatten(1);
        if ($extra->isNotEmpty()) {
            throw new BusinessException('Quét thừa / sai cây: '.$extra->map(fn (PlantUnit $u) => $u->displayCode())->implode(', ').'.');
        }
    }

    /**
     * Hoàn hàng: $quantity chậu đã giao cho dòng đơn quay về kho. Đơn giao trước khi có mã từng chậu thì thiếu chậu:
     * phát mã mới cho phần thiếu (cần in tem dán lại).
     *
     * @return Collection<int, PlantUnit> các chậu vừa về kho (đã khóa)
     */
    public function returnItem(OrderItem $item, int $quantity, StockMovement $movement, ?int $by): Collection
    {
        $units = PlantUnit::where('order_item_id', $item->id)->where('status', PlantUnitStatus::Sold->value)
            ->orderBy('id')->limit($quantity)->lockForUpdate()->get();
        $this->setStatus($units, PlantUnitStatus::InStock, 'returned', $movement->id, $item->id, $by, null);
        $missing = $quantity - $units->count();
        if ($missing > 0) {
            $ids = self::insert($item->plant_id, $missing, $movement->id, null, $by, 'Hàng hoàn của đơn giao trước khi có mã từng chậu: in tem dán lại');
            $units = $units->merge(PlantUnit::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get());
        }

        return $units->values();
    }

    /**
     * Đổi trạng thái các chậu ĐÃ KHÓA (xuất hủy, thất lạc, tìm thấy lại) kèm bút toán kho tương ứng.
     *
     * @param  Collection<int, PlantUnit>  $units
     */
    public function change(Collection $units, PlantUnitStatus $from, PlantUnitStatus $to, string $event, StockMovement $movement, ?int $by, ?string $note = null): void
    {
        foreach ($units as $u) {
            if ($u->plant_id !== $movement->plant_id) {
                throw new LogicException("Chậu {$u->code} không thuộc cây của bút toán.");
            }
            if ($u->status !== $from) {
                throw new BusinessException('Chậu '.$u->displayCode().' đang ở trạng thái "'.$u->status->label().'".');
            }
        }
        $this->setStatus($units, $to, $event, $movement->id, null, $by, $note);
    }

    /** @param Collection<int, PlantUnit> $units */
    private function markShipped(Collection $units, OrderItem $item, ?int $by): void
    {
        $this->setStatus($units, PlantUnitStatus::Sold, 'shipped', null, $item->id, $by, null, $item->id);
    }

    /** @param Collection<int, PlantUnit> $units */
    private function setStatus(Collection $units, PlantUnitStatus $to, string $event, ?int $movementId, ?int $eventOrderItemId, ?int $by, ?string $note, ?int $orderItemId = null): void
    {
        if ($units->isEmpty()) {
            return;
        }
        $now = now();
        $update = ['status' => $to->value, 'status_changed_at' => $now, 'updated_at' => $now];
        if ($to === PlantUnitStatus::Sold) {
            $update['order_item_id'] = $orderItemId;
        }
        PlantUnit::whereIn('id', $units->modelKeys())->update($update);
        DB::table('plant_unit_events')->insert($units->map(fn (PlantUnit $u) => ['plant_unit_id' => $u->id, 'type' => $event,
            'stock_movement_id' => $movementId, 'order_item_id' => $eventOrderItemId, 'note' => $note, 'created_by' => $by, 'created_at' => $now])->all());
        foreach ($units as $u) {
            $u->status = $to;
            if ($to === PlantUnitStatus::Sold) {
                $u->order_item_id = $orderItemId;
            }
        }
    }
}

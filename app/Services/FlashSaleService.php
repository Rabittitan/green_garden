<?php

namespace App\Services;

use App\Enums\FlashSaleStatus;
use App\Exceptions\BusinessException;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Plant;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Flash sale.
 *  - Vòng đời: nhân viên tạo / sửa (nháp, bị từ chối) -> gửi duyệt -> QUẢN TRỊ VIÊN duyệt / từ chối. Quản trị viên không tạo.
 *    Đã duyệt: chỉ quản trị viên hủy. 1 cây không nằm trong 2 đợt đã duyệt trùng giờ (kiểm lúc duyệt).
 *  - Giá: trong khung giờ, đơn giá của dòng = giá sale (PricingService), sau đó vẫn cộng ưu đãi hạng và mã giảm giá.
 *  - Suất: đặt hàng khóa dòng flash_sale_items (sau plants, trước coupons — ADR-003) và tăng sold_count; hủy đơn trả suất.
 */
class FlashSaleService
{
    /**
     * Dòng sale ĐANG CHẠY cho các cây, theo plant_id. Có $lock: khóa dòng (theo id) — chỉ gọi trong transaction đặt / hủy đơn.
     *
     * @param  list<int>  $plantIds
     * @return Collection<int, FlashSaleItem>
     */
    public function activeItems(array $plantIds, bool $lock = false): Collection
    {
        if ($plantIds === []) {
            return collect();
        }
        if ($lock) {
            // Đặt / hủy đơn: chỉ khóa dòng flash_sale_items (khóa ở câu ngoài KHÔNG khóa dòng của truy vấn con flash_sales)
            $rows = FlashSaleItem::whereIn('plant_id', $plantIds)
                ->whereHas('sale', fn ($s) => $s->where('status', FlashSaleStatus::Approved->value)
                    ->where('starts_at', '<=', now())->where('ends_at', '>', now()))
                ->orderBy('id')->lockForUpdate()->get();
        } else {
            // Trang xem / báo giá thử: 1 câu JOIN, dựng sẵn quan hệ sale (tên, giờ) để view không truy vấn thêm
            $rows = FlashSaleItem::join('flash_sales', 'flash_sales.id', '=', 'flash_sale_items.flash_sale_id')
                ->whereIn('flash_sale_items.plant_id', $plantIds)
                ->where('flash_sales.status', FlashSaleStatus::Approved->value)
                ->where('flash_sales.starts_at', '<=', now())->where('flash_sales.ends_at', '>', now())
                ->orderBy('flash_sale_items.id')
                ->select('flash_sale_items.*', 'flash_sales.name as fs_name', 'flash_sales.starts_at as fs_starts_at', 'flash_sales.ends_at as fs_ends_at')
                ->get()->each(function (FlashSaleItem $item) {
                    $sale = (new FlashSale)->forceFill(['id' => $item->flash_sale_id, 'name' => $item->getAttribute('fs_name'),
                        'starts_at' => $item->getAttribute('fs_starts_at'), 'ends_at' => $item->getAttribute('fs_ends_at'), 'status' => FlashSaleStatus::Approved]);
                    $sale->exists = true;
                    $item->setRelation('sale', $sale);
                });
        }

        return $rows->groupBy('plant_id')->map(fn (Collection $group) => $group->sortBy('sale_price')->first());
    }

    /** Dòng đã khóa: ghi nhận đã bán $qty suất. */
    public function consume(FlashSaleItem $item, int $qty): void
    {
        if (! $item->covers($qty)) {
            throw new BusinessException('Suất flash sale vừa hết, vui lòng xem lại giá.');
        }
        FlashSaleItem::whereKey($item->id)->increment('sold_count', $qty);
    }

    /** Hủy đơn (đã khóa đơn + cây): trả lại suất sale của các dòng hàng. */
    public function release(Order $order): void
    {
        $lines = $order->items()->whereNotNull('flash_sale_item_id')->get(['flash_sale_item_id', 'quantity']);
        if ($lines->isEmpty()) {
            return;
        }
        $locked = FlashSaleItem::whereIn('id', $lines->pluck('flash_sale_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($lines as $line) {
            $item = $locked[$line->flash_sale_item_id];
            FlashSaleItem::whereKey($item->id)->update(['sold_count' => max(0, $item->sold_count - $line->quantity)]);
            $item->sold_count = max(0, $item->sold_count - $line->quantity);
        }
    }

    // ================================================================ VÒNG ĐỜI ĐỢT SALE

    /**
     * @param  array{name: string, starts_at: string, ends_at: string}  $data
     * @param  array<int, array{sale_price: int, quantity_limit: int|null}>  $items  plant_id => ...
     */
    public function create(User $by, array $data, array $items): FlashSale
    {
        if ($by->isAdmin()) {
            throw new BusinessException('Quản trị viên chỉ duyệt flash sale. Đợt sale do nhân viên tạo.');
        }

        return DB::transaction(function () use ($by, $data, $items) {
            $sale = new FlashSale($this->window($data));
            $sale->created_by = $by->id;
            $sale->status = FlashSaleStatus::Draft;
            $sale->save();
            $this->replaceItems($sale, $items);

            return $sale;
        }, 3);
    }

    /**
     * @param  array{name: string, starts_at: string, ends_at: string}  $data
     * @param  array<int, array{sale_price: int, quantity_limit: int|null}>  $items
     */
    public function update(FlashSale $sale, User $by, array $data, array $items): FlashSale
    {
        return $this->locked($sale, function (FlashSale $s) use ($by, $data, $items) {
            if (! $s->status->isEditable() || $by->isAdmin()) {
                throw new BusinessException('Chỉ nhân viên sửa được đợt sale ở trạng thái nháp hoặc bị từ chối.');
            }
            $s->fill($this->window($data));
            $s->status = FlashSaleStatus::Draft;                              // sửa xong phải gửi duyệt lại
            $s->save();
            $this->replaceItems($s, $items);

            return $s;
        });
    }

    public function submit(FlashSale $sale, User $by): FlashSale
    {
        return $this->locked($sale, function (FlashSale $s) use ($by) {
            if (! $s->status->isEditable() || $by->isAdmin()) {
                throw new BusinessException('Chỉ gửi duyệt được đợt sale nháp / bị từ chối.');
            }
            if ($s->items()->doesntExist()) {
                throw new BusinessException('Đợt sale phải có ít nhất 1 cây.');
            }
            if ($s->ends_at->isPast()) {
                throw new BusinessException('Đợt sale đã qua giờ kết thúc, hãy sửa lại thời gian.');
            }
            $s->status = FlashSaleStatus::Submitted;
            $s->reject_reason = null;
            $s->save();

            return $s;
        });
    }

    public function approve(FlashSale $sale, User $admin): FlashSale
    {
        return $this->locked($sale, function (FlashSale $s) use ($admin) {
            if (! $admin->isAdmin() || $s->status !== FlashSaleStatus::Submitted) {
                throw new BusinessException('Chỉ quản trị viên duyệt được đợt sale đang chờ duyệt.');
            }
            if ($s->created_by === $admin->id) {
                throw new BusinessException('Người tạo đợt sale không được tự duyệt.');
            }
            if ($s->ends_at->isPast()) {
                throw new BusinessException('Đợt sale đã qua giờ kết thúc, không duyệt được.');
            }
            $items = $s->items()->with('plant')->get();
            foreach ($items as $it) {
                if ($it->sale_price >= $it->plant->price) {
                    throw new BusinessException("Giá sale của \"{$it->plant->name}\" phải thấp hơn giá bán hiện tại (".Format::money($it->plant->price).').');
                }
            }
            $clash = FlashSaleItem::with(['sale', 'plant'])->whereIn('plant_id', $items->pluck('plant_id'))
                ->where('flash_sale_id', '<>', $s->id)
                ->whereHas('sale', fn ($q) => $q->where('status', FlashSaleStatus::Approved->value)
                    ->where('starts_at', '<', $s->ends_at)->where('ends_at', '>', $s->starts_at))
                ->first();
            if ($clash) {
                throw new BusinessException("Cây \"{$clash->plant->name}\" đã có trong đợt \"{$clash->sale->name}\" trùng khung giờ.");
            }
            $s->status = FlashSaleStatus::Approved;
            $s->approved_by = $admin->id;
            $s->approved_at = now();
            $s->save();

            return $s;
        });
    }

    public function reject(FlashSale $sale, User $admin, string $reason): FlashSale
    {
        return $this->locked($sale, function (FlashSale $s) use ($admin, $reason) {
            if (! $admin->isAdmin() || $s->status !== FlashSaleStatus::Submitted) {
                throw new BusinessException('Chỉ quản trị viên từ chối được đợt sale đang chờ duyệt.');
            }
            if (trim($reason) === '') {
                throw new BusinessException('Phải ghi lý do từ chối.');
            }
            $s->status = FlashSaleStatus::Rejected;
            $s->reject_reason = mb_substr($reason, 0, 255);
            $s->save();

            return $s;
        });
    }

    /** Hủy đợt: nhân viên tạo hủy được khi chưa duyệt; đợt đã duyệt chỉ quản trị viên hủy (dừng sale ngay). Đơn đã đặt giữ giá sale. */
    public function cancel(FlashSale $sale, User $by): FlashSale
    {
        return $this->locked($sale, function (FlashSale $s) use ($by) {
            $ok = match ($s->status) {
                FlashSaleStatus::Draft, FlashSaleStatus::Submitted, FlashSaleStatus::Rejected => $by->isAdmin() || $s->created_by === $by->id,
                FlashSaleStatus::Approved => $by->isAdmin(),
                FlashSaleStatus::Cancelled => false,
            };
            if (! $ok) {
                throw new BusinessException('Bạn không hủy được đợt sale này.');
            }
            $s->status = FlashSaleStatus::Cancelled;
            $s->save();

            return $s;
        });
    }

    /**
     * Giờ nhập theo giờ Việt Nam -> UTC.
     *
     * @param  array{name: string, starts_at: string, ends_at: string}  $data
     * @return array{name: string, starts_at: Carbon, ends_at: Carbon}
     */
    private function window(array $data): array
    {
        $tz = (string) config('shop.display_timezone');
        $start = Carbon::parse($data['starts_at'], $tz)->utc();
        $end = Carbon::parse($data['ends_at'], $tz)->utc();
        if ($end->lte($start)) {
            throw new BusinessException('Giờ kết thúc phải sau giờ bắt đầu.');
        }

        return ['name' => $data['name'], 'starts_at' => $start, 'ends_at' => $end];
    }

    /** @param array<int, array{sale_price: int, quantity_limit: int|null}> $items */
    private function replaceItems(FlashSale $sale, array $items): void
    {
        $items = array_filter($items, fn ($i) => $i['sale_price'] > 0);
        if ($items === []) {
            throw new BusinessException('Chọn ít nhất 1 cây và nhập giá sale.');
        }
        $plants = Plant::whereIn('id', array_keys($items))->get()->keyBy('id');
        $sale->items()->delete();
        foreach ($items as $plantId => $i) {
            $plant = $plants->get($plantId) ?? throw new BusinessException('Có cây không tồn tại.');
            if ($i['sale_price'] >= $plant->price) {
                throw new BusinessException("Giá sale của \"{$plant->name}\" phải thấp hơn giá bán (".Format::money($plant->price).').');
            }
            $sale->items()->create(['plant_id' => $plantId, 'sale_price' => $i['sale_price'], 'quantity_limit' => $i['quantity_limit']]);
        }
    }

    private function locked(FlashSale $sale, callable $fn): FlashSale
    {
        return DB::transaction(fn () => $fn(FlashSale::whereKey($sale->id)->lockForUpdate()->firstOrFail()), 3);
    }
}

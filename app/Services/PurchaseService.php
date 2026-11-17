<?php

namespace App\Services;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\GoodsReceipt;
use App\Models\Plant;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * NHẬP HÀNG: yêu cầu nhập (đề xuất -> gửi duyệt -> duyệt 4 mắt -> đặt NCC) -> phiếu nhập (hàng thực về) -> cộng kho.
 *
 *  - Yêu cầu nhập KHÔNG đổi kho. Chỉ phiếu nhập "hoàn tất" mới cộng kho + tính lại giá vốn bình quân.
 *  - Phiếu theo yêu cầu: chỉ được nhập cây có trong yêu cầu, không vượt số đã duyệt còn lại.
 *  - Thứ tự khóa: phiếu nhập -> yêu cầu -> cây (id tăng dần) -> dòng yêu cầu.
 *    (cây khóa theo cùng thứ tự với đặt hàng nên nhập hàng song song với bán hàng không deadlock)
 */
class PurchaseService
{
    public function __construct(private InventoryService $inventory) {}

    // ================================================================ YÊU CẦU NHẬP

    /**
     * @param  array<int, array{quantity:int, estimated_unit_cost:int, note?:?string}>  $items  plant_id => ...
     * @param  array{reason?:?string, needed_by?:?string, priority?:string, supplier_id?:?int}  $meta
     */
    public function createRequest(User $by, array $items, array $meta = []): PurchaseRequest
    {
        return DB::transaction(function () use ($by, $items, $meta) {
            $pr = $this->createWithCode('YCN', 'purchase_requests', fn (string $code) => PurchaseRequest::create([
                'code' => $code, 'requested_by' => $by->id, 'status' => PurchaseRequestStatus::Draft,
                'reason' => $meta['reason'] ?? null, 'needed_by' => $meta['needed_by'] ?? null,
                'priority' => $meta['priority'] ?? 'normal', 'supplier_id' => $meta['supplier_id'] ?? null,
            ]));
            $this->replaceItems($pr, $items);

            return $pr;
        }, 3);
    }

    /**
     * Sửa yêu cầu khi còn nháp (gồm cả khi bị trả về từ "chờ duyệt").
     *
     * @param  array<int, array{quantity:int, estimated_unit_cost:int, note?:?string}>  $items
     * @param  array<string, mixed>  $meta
     */
    public function updateDraft(PurchaseRequest $pr, array $items, array $meta, User $by): PurchaseRequest
    {
        return DB::transaction(function () use ($pr, $items, $meta) {
            $pr = PurchaseRequest::lockForUpdate()->findOrFail($pr->id);
            if ($pr->status !== PurchaseRequestStatus::Draft) {
                throw new BusinessException('Chỉ sửa được yêu cầu đang ở trạng thái nháp.');
            }
            $pr->update(array_intersect_key($meta, array_flip(['reason', 'needed_by', 'priority', 'supplier_id'])));
            $this->replaceItems($pr, $items);

            return $pr;
        }, 3);
    }

    /** @param array<int, array{quantity:int, estimated_unit_cost:int, note?:?string}> $items */
    private function replaceItems(PurchaseRequest $pr, array $items): void
    {
        $items = array_filter($items, fn ($i) => $i['quantity'] > 0);
        if ($items === []) {
            throw new BusinessException('Yêu cầu nhập phải có ít nhất 1 cây với số lượng lớn hơn 0.');
        }
        $plants = Plant::whereIn('id', array_keys($items))->get()->keyBy('id');
        if ($plants->count() !== count($items)) {
            throw new BusinessException('Có cây không tồn tại hoặc đã bị xóa.');
        }

        $pr->items()->delete();
        $total = 0;
        foreach ($items as $plantId => $i) {
            $qty = (int) $i['quantity'];
            $cost = max(0, $i['estimated_unit_cost']);
            $pr->items()->create([
                'plant_id' => $plantId, 'quantity_requested' => $qty, 'estimated_unit_cost' => $cost,
                'stock_at_request' => $plants[$plantId]->stock, 'note' => $i['note'] ?? null,
            ]);
            $total += $qty * $cost;
        }
        $pr->update(['estimated_total' => $total]);
    }

    /** Gợi ý tạo yêu cầu nhập từ các cây đang dưới ngưỡng reorder_point. */
    public function draftFromLowStock(User $user, int $targetMultiplier = 3): ?PurchaseRequest
    {
        $plants = Plant::active()->needsReorder()->get();
        if ($plants->isEmpty()) {
            return null;
        }
        $items = [];
        foreach ($plants as $p) {
            $items[$p->id] = ['quantity' => max(1, $p->reorder_point * $targetMultiplier - $p->stock), 'estimated_unit_cost' => $p->cost_price];
        }

        return $this->createRequest($user, $items, ['reason' => 'Tự động đề xuất từ cây dưới ngưỡng tồn kho']);
    }

    /**
     * Duyệt (4 mắt): người duyệt khác người tạo, được giảm số lượng từng dòng (0 = bỏ dòng).
     *
     * @param  array<int, int>  $approvedQuantities  purchase_request_item_id => số duyệt
     */
    public function approve(PurchaseRequest $pr, User $by, array $approvedQuantities = []): void
    {
        DB::transaction(function () use ($pr, $by, $approvedQuantities) {
            $pr = PurchaseRequest::lockForUpdate()->findOrFail($pr->id);
            $this->assertCanTransition($pr, PurchaseRequestStatus::Approved, $by);

            $total = 0;
            $kept = 0;
            foreach ($pr->items()->lockForUpdate()->get() as $item) {
                $qty = array_key_exists($item->id, $approvedQuantities) ? (int) $approvedQuantities[$item->id] : $item->quantity_requested;
                if ($qty < 0 || $qty > $item->quantity_requested) {
                    throw new BusinessException("Số duyệt của dòng #{$item->id} phải từ 0 đến {$item->quantity_requested}.");
                }
                $item->update(['quantity_approved' => $qty]);
                $total += $qty * $item->estimated_unit_cost;
                $kept += $qty > 0 ? 1 : 0;
            }
            if ($kept === 0) {
                throw new BusinessException('Không duyệt dòng nào: hãy dùng "Từ chối" thay vì duyệt 0.');
            }
            $pr->update(['status' => PurchaseRequestStatus::Approved, 'approved_by' => $by->id, 'approved_at' => now(), 'estimated_total' => $total]);
        }, 3);
    }

    /** Đặt hàng với nhà cung cấp. */
    public function markOrdered(PurchaseRequest $pr, Supplier $supplier, User $by): void
    {
        if (! $supplier->is_active) {
            throw new BusinessException('Nhà cung cấp đang ngừng hợp tác.');
        }
        $this->transition($pr, PurchaseRequestStatus::Ordered, $by, null, ['supplier_id' => $supplier->id]);
    }

    /**
     * Chuyển trạng thái thủ công: gửi duyệt, trả về nháp, từ chối, hủy, đóng (chấp nhận nhận thiếu).
     *
     * @param  array<string, mixed>  $extra
     */
    public function transition(PurchaseRequest $pr, PurchaseRequestStatus $to, User $by, ?string $reason = null, array $extra = []): void
    {
        if ($to === PurchaseRequestStatus::Approved) {
            $this->approve($pr, $by);

            return;
        }
        DB::transaction(function () use ($pr, $to, $by, $reason, $extra) {
            $pr = PurchaseRequest::lockForUpdate()->findOrFail($pr->id);
            $this->assertCanTransition($pr, $to, $by);

            $data = ['status' => $to] + $extra;
            if ($to === PurchaseRequestStatus::Submitted) {
                if (! $pr->items()->exists()) {
                    throw new BusinessException('Yêu cầu chưa có dòng hàng nào.');
                }
                $data['submitted_at'] = now();
            }
            if (in_array($to, [PurchaseRequestStatus::Rejected, PurchaseRequestStatus::Cancelled], true) || $pr->status === PurchaseRequestStatus::PartiallyReceived) {
                if (trim((string) $reason) === '') {
                    throw new BusinessException('Phải ghi lý do.');
                }
                $data['rejected_reason'] = mb_substr((string) $reason, 0, 255);
            }
            if ($to === PurchaseRequestStatus::Cancelled && GoodsReceipt::where('purchase_request_id', $pr->id)->where('status', GoodsReceiptStatus::Draft->value)->exists()) {
                throw new BusinessException('Còn phiếu nhập nháp của yêu cầu này: hãy hủy phiếu trước.');
            }
            $pr->update($data);
        }, 3);
    }

    private function assertCanTransition(PurchaseRequest $pr, PurchaseRequestStatus $to, User $by): void
    {
        if (! $pr->status->canTransitionTo($to)) {
            throw new BusinessException("Không thể chuyển yêu cầu {$pr->code} từ \"{$pr->status->label()}\" sang \"{$to->label()}\".");
        }
        if (in_array($to, [PurchaseRequestStatus::Approved, PurchaseRequestStatus::Rejected], true) && $pr->requested_by === $by->id) {
            throw new BusinessException('Người tạo yêu cầu không được tự duyệt / từ chối (nguyên tắc 4 mắt).');
        }
    }

    // ================================================================ PHIẾU NHẬP

    /**
     * Lập phiếu nhập (nháp). Chưa đổi kho.
     *
     * @param  array<int, array{quantity_received:int, quantity_rejected?:int, unit_cost:int, note?:?string}>  $lines  plant_id => ...
     */
    public function createReceipt(User $by, Supplier $supplier, ?PurchaseRequest $pr, array $lines, ?string $invoiceNo = null, ?string $note = null): GoodsReceipt
    {
        if (! $supplier->is_active) {
            throw new BusinessException('Nhà cung cấp đang ngừng hợp tác.');
        }
        $lines = array_filter($lines, fn ($l) => $l['quantity_received'] + ($l['quantity_rejected'] ?? 0) > 0);
        if ($lines === []) {
            throw new BusinessException('Phiếu nhập phải có ít nhất 1 dòng có số lượng.');
        }

        return DB::transaction(function () use ($by, $supplier, $pr, $lines, $invoiceNo, $note) {
            $prItems = collect();
            if ($pr) {
                $pr = PurchaseRequest::lockForUpdate()->findOrFail($pr->id);
                if (! $pr->status->isReceivable()) {
                    throw new BusinessException("Yêu cầu {$pr->code} đang \"{$pr->status->label()}\", không lập phiếu nhập được.");
                }
                $prItems = $pr->items()->get()->keyBy('plant_id');
            }
            $plants = Plant::whereIn('id', array_keys($lines))->get()->keyBy('id');

            foreach ($lines as $plantId => $l) {
                if (! $plants->has($plantId)) {
                    throw new BusinessException('Có cây không tồn tại hoặc đã bị xóa.');
                }
                if ((int) $l['quantity_received'] < 0 || (int) ($l['quantity_rejected'] ?? 0) < 0 || (int) $l['unit_cost'] < 0) {
                    throw new BusinessException('Số lượng và đơn giá không được âm.');
                }
                if ($pr) {
                    $item = $prItems->get($plantId) ?? throw new BusinessException("Cây \"{$plants[$plantId]->name}\" không có trong yêu cầu {$pr->code}.");
                    if ((int) $l['quantity_received'] > $item->remaining) {
                        throw new BusinessException("Cây \"{$plants[$plantId]->name}\": nhận {$l['quantity_received']} vượt số còn phải nhận ({$item->remaining}).");
                    }
                }
            }

            $receipt = $this->createWithCode('PN', 'goods_receipts', fn (string $code) => GoodsReceipt::create([
                'code' => $code, 'purchase_request_id' => $pr?->id, 'supplier_id' => $supplier->id,
                'status' => GoodsReceiptStatus::Draft, 'supplier_invoice_no' => $invoiceNo, 'received_by' => $by->id, 'note' => $note,
            ]));
            $total = 0;
            foreach ($lines as $plantId => $l) {
                $receipt->items()->create([
                    'plant_id' => $plantId, 'purchase_request_item_id' => $pr ? $prItems[$plantId]->id : null,
                    'quantity_received' => (int) $l['quantity_received'], 'quantity_rejected' => (int) ($l['quantity_rejected'] ?? 0),
                    'unit_cost' => (int) $l['unit_cost'], 'note' => $l['note'] ?? null,
                ]);
                $total += (int) $l['quantity_received'] * (int) $l['unit_cost'];
            }
            $receipt->update(['total_cost' => $total]);

            return $receipt;
        }, 3);
    }

    /** Hoàn tất phiếu nhập: cộng kho, ghi sổ kho, cập nhật giá vốn, số đã nhận và trạng thái yêu cầu. */
    public function completeReceipt(GoodsReceipt $receipt, ?int $by = null): void
    {
        DB::transaction(function () use ($receipt, $by) {
            $receipt = GoodsReceipt::lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status !== GoodsReceiptStatus::Draft) {
                throw new BusinessException('Phiếu nhập đã được xử lý.');
            }

            // Khóa yêu cầu TRƯỚC cây / dòng yêu cầu (cùng thứ tự với duyệt / hủy yêu cầu)
            $pr = $receipt->purchase_request_id ? PurchaseRequest::lockForUpdate()->findOrFail($receipt->purchase_request_id) : null;
            if ($pr && ! $pr->status->isReceivable()) {
                throw new BusinessException("Yêu cầu {$pr->code} đang \"{$pr->status->label()}\", không nhập kho được.");
            }

            $items = $receipt->items()->orderBy('plant_id')->get();
            $plants = $this->inventory->lockPlants($items->pluck('plant_id')->all());

            $total = 0;
            foreach ($items as $item) {
                if ($item->purchase_request_item_id) {
                    $pri = PurchaseRequestItem::lockForUpdate()->findOrFail($item->purchase_request_item_id);
                    if ($item->quantity_received > $pri->remaining) {   // phiếu khác đã nhập trước
                        throw new BusinessException("Số nhập của \"{$plants[$item->plant_id]->name}\" vượt số còn phải nhận ({$pri->remaining}).");
                    }
                    $pri->increment('quantity_received', $item->quantity_received);
                }
                if ($item->quantity_received > 0) {
                    $this->inventory->move($plants[$item->plant_id], StockMovementType::PurchaseReceipt, $item->quantity_received,
                        receiptItem: $item, unitCost: $item->unit_cost, note: "Nhập kho {$receipt->code}", by: $by);
                }
                $total += $item->quantity_received * $item->unit_cost;
            }

            $receipt->update(['status' => GoodsReceiptStatus::Completed, 'received_at' => now(), 'total_cost' => $total]);

            if ($pr) {
                $done = $pr->items()->get()->every(fn ($i) => $i->remaining === 0);
                $pr->update(['status' => $done ? PurchaseRequestStatus::Received : PurchaseRequestStatus::PartiallyReceived]);
            }
        }, 3);
    }

    public function cancelReceipt(GoodsReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $receipt = GoodsReceipt::lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status !== GoodsReceiptStatus::Draft) {
                throw new BusinessException('Chỉ hủy được phiếu nhập nháp (phiếu đã nhập kho thì điều chỉnh bằng kiểm kê).');
            }
            $receipt->update(['status' => GoodsReceiptStatus::Cancelled]);
        }, 3);
    }

    // ================================================================ mã chứng từ

    /**
     * Tạo chứng từ với mã tăng dần dạng YCN-2611-0001 (đánh số lại theo tháng, giờ VN).
     * Hai giao dịch song song có thể đọc cùng số lớn nhất -> trùng UNIQUE: thử lại với số kế tiếp.
     * (MySQL chỉ rollback câu INSERT lỗi, transaction bên ngoài vẫn tiếp tục.)
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  callable(string): T  $create
     * @return T
     */
    public function createWithCode(string $prefix, string $table, callable $create)
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $create($this->nextCode($prefix, $table));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 10 || ! str_contains($e->getMessage(), "{$table}_code_unique")) {
                    throw $e;
                }
                usleep(random_int(1000, 20000));
            }
        }
    }

    public function nextCode(string $prefix, string $table): string
    {
        $base = $prefix.'-'.now(config('shop.display_timezone'))->format('ym').'-';
        $last = DB::table($table)->where('code', 'like', $base.'%')->max('code');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $base.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

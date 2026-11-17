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
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Yêu cầu nhập hàng -> duyệt -> phiếu nhập kho -> cộng kho. */
class PurchaseService
{
    public function __construct(private InventoryService $inventory) {}

    /** Gợi ý tạo yêu cầu nhập từ các cây đang dưới ngưỡng reorder_point. */
    public function draftFromLowStock(User $user, int $targetMultiplier = 3): ?PurchaseRequest
    {
        $plants = Plant::active()->needsReorder()->get();
        if ($plants->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($user, $plants, $targetMultiplier) {
            $pr = $this->createWithCode('YCN', 'purchase_requests', fn (string $code) => PurchaseRequest::create([
                'code' => $code,
                'requested_by' => $user->id,
                'status' => PurchaseRequestStatus::Draft,
                'reason' => 'Tự động đề xuất từ cây dưới ngưỡng tồn kho',
            ]));
            $total = 0;
            foreach ($plants as $p) {
                $qty = max(1, $p->reorder_point * $targetMultiplier - $p->stock);
                $pr->items()->create([
                    'plant_id' => $p->id, 'quantity_requested' => $qty,
                    'estimated_unit_cost' => $p->cost_price, 'stock_at_request' => $p->stock,
                ]);
                $total += $qty * $p->cost_price;
            }
            $pr->update(['estimated_total' => $total]);

            return $pr;
        }, 3);
    }

    public function transition(PurchaseRequest $pr, PurchaseRequestStatus $to, User $by, ?string $reason = null): void
    {
        DB::transaction(function () use ($pr, $to, $by, $reason) {
            $pr = PurchaseRequest::lockForUpdate()->findOrFail($pr->id);
            if (! $pr->status->canTransitionTo($to)) {
                throw new BusinessException("Không thể chuyển từ \"{$pr->status->label()}\" sang \"{$to->label()}\".");
            }
            if ($to === PurchaseRequestStatus::Approved && $pr->requested_by === $by->id) {
                throw new BusinessException('Người tạo yêu cầu không được tự duyệt.');   // tách quyền (4 mắt)
            }

            $data = ['status' => $to];
            if ($to === PurchaseRequestStatus::Submitted) {
                $data['submitted_at'] = now();
            }
            if ($to === PurchaseRequestStatus::Approved) {
                $data += ['approved_by' => $by->id, 'approved_at' => now()];
                $pr->items()->whereNull('quantity_approved')->update(['quantity_approved' => DB::raw('quantity_requested')]);
            }
            if ($to === PurchaseRequestStatus::Rejected) {
                $data['rejected_reason'] = $reason;
            }
            $pr->update($data);
        });
    }

    /** Hoàn tất phiếu nhập: cộng kho, ghi sổ kho, cập nhật số đã nhận và trạng thái yêu cầu. */
    public function completeReceipt(GoodsReceipt $receipt, ?int $by = null): void
    {
        DB::transaction(function () use ($receipt, $by) {
            $receipt = GoodsReceipt::lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status !== GoodsReceiptStatus::Draft) {
                throw new BusinessException('Phiếu nhập đã được xử lý.');
            }

            // Khóa cây theo id tăng dần (cùng thứ tự với đặt hàng) để không deadlock
            $items = $receipt->items()->orderBy('plant_id')->get();
            $plants = $this->inventory->lockPlants($items->pluck('plant_id')->all());

            $total = 0;
            foreach ($items as $item) {
                if ($item->quantity_received > 0) {
                    $this->inventory->move($plants[$item->plant_id], StockMovementType::PurchaseReceipt, $item->quantity_received,
                        receiptItem: $item, unitCost: $item->unit_cost, note: "Nhập kho {$receipt->code}", by: $by);
                }
                if ($item->purchase_request_item_id) {
                    $pri = PurchaseRequestItem::lockForUpdate()->findOrFail($item->purchase_request_item_id);
                    if ($item->quantity_received > $pri->remaining) {
                        throw new BusinessException("Số nhập của \"{$plants[$item->plant_id]->name}\" vượt số đã duyệt.");
                    }
                    $pri->increment('quantity_received', $item->quantity_received);
                }
                $total += $item->quantity_received * $item->unit_cost;
            }

            $receipt->update(['status' => GoodsReceiptStatus::Completed, 'received_at' => now(), 'total_cost' => $total]);

            if ($receipt->purchase_request_id) {
                $pr = PurchaseRequest::with('items')->lockForUpdate()->findOrFail($receipt->purchase_request_id);
                $done = $pr->items->every(fn ($i) => $i->remaining === 0);
                $pr->update(['status' => $done ? PurchaseRequestStatus::Received : PurchaseRequestStatus::PartiallyReceived]);
            }
        }, 3);
    }

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

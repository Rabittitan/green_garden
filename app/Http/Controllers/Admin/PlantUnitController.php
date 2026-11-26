<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlantUnitStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Plant;
use App\Models\PlantUnit;
use App\Support\UnitCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Từng chậu có mã riêng: tra cứu lịch sử, in tem QR, kiểm tra mã khi quét. Chỉ đọc. */
class PlantUnitController extends Controller
{
    /** Kho → Tra cứu chậu: quét / gõ mã để xem lịch sử; hoặc lọc chậu của một cây theo trạng thái. */
    public function index(Request $request): View
    {
        $code = UnitCode::normalize((string) $request->query('ma', ''));
        $unit = $code ? PlantUnit::with(['plant', 'receiptItem.receipt.supplier', 'orderItem.order',
            'events' => fn ($q) => $q->with(['creator', 'orderItem.order', 'movement'])])->firstWhere('code', $code) : null;

        $plant = $request->filled('plant') ? Plant::withTrashed()->find((int) $request->query('plant')) : null;
        $status = PlantUnitStatus::tryFrom((string) $request->query('status', 'in_stock')) ?? PlantUnitStatus::InStock;
        $units = $plant ? $plant->units()->where('status', $status->value)->orderBy('id')->paginate(50)->withQueryString() : null;
        $counts = $plant ? $plant->units()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status') : collect();

        return view('admin.units.index', [
            'query' => (string) $request->query('ma', ''), 'unit' => $unit,
            'plant' => $plant, 'status' => $status, 'units' => $units, 'counts' => $counts,
        ]);
    }

    /**
     * Trang in tem (máy in tem nhiệt, mỗi tem 1 trang 50×30 mm). Chọn chậu theo:
     * ?receipt=<phiếu nhập> (chậu của phiếu) | ?plant=<cây> (chậu đang trong kho) | ?codes=MA1,MA2 (in lại vài tem).
     */
    public function labels(Request $request): View
    {
        $query = PlantUnit::with('plant')->orderBy('plant_id')->orderBy('id');
        $title = 'Chưa chọn chậu nào để in';
        if ($request->filled('receipt') && ($receipt = GoodsReceipt::find((int) $request->query('receipt')))) {
            $query->whereIn('goods_receipt_item_id', $receipt->items()->select('id'));
            $title = "Tem chậu của phiếu nhập {$receipt->code}";
        } elseif ($request->filled('plant') && ($plant = Plant::withTrashed()->find((int) $request->query('plant')))) {
            $query->where('plant_id', $plant->id)->where('status', PlantUnitStatus::InStock->value);
            $title = "Tem các chậu \"{$plant->name}\" đang trong kho";
        } elseif ($request->filled('codes')) {
            $codes = array_values(array_filter(array_map(fn ($c) => UnitCode::normalize($c), explode(',', (string) $request->query('codes')))));
            $query->whereIn('code', $codes);
            $title = 'In lại tem';
        } else {
            $query->whereRaw('1 = 0');
        }

        return view('admin.units.labels', ['units' => $query->limit(2000)->get(), 'title' => $title]);
    }

    /** Máy quét gọi sau mỗi lần quét: chậu nào, cây gì, đang ở đâu. Không đổi dữ liệu. */
    public function check(Request $request): JsonResponse
    {
        $code = UnitCode::normalize((string) $request->query('code', ''));
        if ($code === null) {
            return response()->json(['message' => 'Không đọc được mã chậu (mã gồm 8 ký tự, ví dụ 7KQ2-M9XA).'], 422);
        }
        $unit = PlantUnit::with('plant')->firstWhere('code', $code);
        if ($unit === null) {
            return response()->json(['message' => 'Không có chậu mang mã '.UnitCode::format($code).'.'], 404);
        }

        return response()->json([
            'code' => $unit->code, 'display' => $unit->displayCode(),
            'plant_id' => $unit->plant_id, 'plant_name' => $unit->plant->name,
            'status' => $unit->status->value, 'status_label' => $unit->status->label(),
        ]);
    }
}

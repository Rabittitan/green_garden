<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlantUnitStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StockCount;
use App\Services\StockCountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Phiếu kiểm kê (chỉ quản trị viên): lập phiếu, quét mã từng chậu, xem báo cáo chênh lệch, chốt để điều chỉnh kho. */
class StockCountController extends Controller
{
    public function __construct(private StockCountService $counts) {}

    public function index(): View
    {
        return view('admin.stock-counts.index', [
            'counts' => StockCount::with(['creator', 'category'])->withCount('items')->latest('id')->paginate(20),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['nullable', 'exists:categories,id'], 'note' => ['nullable', 'string', 'max:255']]);
        try {
            $count = $this->counts->create($request->user(), $data['category_id'] ?? null, $data['note'] ?? null);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-counts.show', $count)->with('success', "Đã lập phiếu {$count->code}. Nhập số đếm thực tế rồi bấm Lưu.");
    }

    public function show(StockCount $stockCount): View
    {
        $stockCount->load(['items' => fn ($q) => $q->with('plant')->orderBy('id'), 'creator', 'completer', 'category']);

        return view('admin.stock-counts.show', [
            'count' => $stockCount,
            'preview' => $stockCount->status === 'draft' ? $this->counts->preview($stockCount) : [],
        ]);
    }

    /** Lưu ghi chú từng dòng, hoặc chốt phiếu (action=complete). Số đếm lấy từ các lần quét, không gõ tay. */
    public function update(Request $request, StockCount $stockCount): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'array'], 'notes.*' => ['nullable', 'string', 'max:255']]);
        try {
            $this->counts->saveNotes($stockCount, $request->user(), array_map(fn ($n) => $n === null ? null : (string) $n, $data['notes'] ?? []));
            if ($request->input('action') === 'complete') {
                $this->counts->complete($stockCount, $request->user());

                return redirect()->route('admin.stock-counts.show', $stockCount)->with('success', 'Đã chốt phiếu: chậu không thấy đã ghi thất lạc, chậu tìm lại đã về kho.');
            }
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-counts.show', $stockCount)->with('success', 'Đã lưu ghi chú.');
    }

    /** Quét 1 chậu (gọi từ trang phiếu sau mỗi lần quét). */
    public function scan(Request $request, StockCount $stockCount): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20'], 'remove' => ['nullable', 'boolean']]);
        try {
            if ($request->boolean('remove')) {
                $this->counts->unscan($stockCount, $request->user(), $data['code']);

                return response()->json(['ok' => true, 'removed' => true]);
            }
            ['unit' => $unit, 'item' => $item] = $this->counts->scan($stockCount, $request->user(), $data['code']);
        } catch (BusinessException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'item' => $item->id, 'code' => $unit->code, 'display' => $unit->displayCode(), 'plant_name' => $unit->plant->name,
            'found' => $unit->status === PlantUnitStatus::Lost]);
    }

    public function cancel(Request $request, StockCount $stockCount): RedirectResponse
    {
        try {
            $this->counts->cancel($stockCount, $request->user());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-counts.index')->with('success', 'Đã hủy phiếu kiểm kê (kho không thay đổi).');
    }
}

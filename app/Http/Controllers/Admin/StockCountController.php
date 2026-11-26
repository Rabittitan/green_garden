<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StockCount;
use App\Services\StockCountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Phiếu kiểm kê (chỉ quản trị viên): lập phiếu, nhập số đếm, xem báo cáo chênh lệch, chốt để điều chỉnh kho. */
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

        return view('admin.stock-counts.show', ['count' => $stockCount]);
    }

    public function update(Request $request, StockCount $stockCount): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.counted_qty' => ['nullable', 'integer', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ], [], ['lines.*.counted_qty' => 'số đếm']);
        $lines = [];
        foreach ($data['lines'] as $id => $l) {
            $lines[(int) $id] = ['counted_qty' => isset($l['counted_qty']) ? (int) $l['counted_qty'] : null, 'note' => $l['note'] ?? null];
        }
        try {
            $this->counts->saveCounts($stockCount, $request->user(), $lines);
            if ($request->input('action') === 'complete') {
                $this->counts->complete($stockCount, $request->user());

                return redirect()->route('admin.stock-counts.show', $stockCount)->with('success', 'Đã chốt phiếu: tồn kho đã được điều chỉnh theo chênh lệch.');
            }
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-counts.show', $stockCount)->with('success', 'Đã lưu số đếm.');
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

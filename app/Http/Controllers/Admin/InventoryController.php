<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Kho: tồn hiện tại, sổ kho từng cây, ghi hư hỏng / cây chết. Kiểm kê: phiếu kiểm kê riêng (StockCountController). */
class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $plants = Plant::with('category')
            ->when($request->boolean('low'), fn ($q) => $q->needsReorder())
            ->orderByRaw('stock <= reorder_point DESC')->orderBy('stock')->paginate(20)->withQueryString();

        return view('admin.inventory.index', compact('plants'));
    }

    public function show(Plant $plant): View
    {
        $movements = $plant->stockMovements()->with(['orderItem.order', 'goodsReceiptItem.receipt', 'creator'])
            ->latest('id')->paginate(30);

        return view('admin.inventory.show', compact('plant', 'movements'));
    }

    public function damage(Request $request, Plant $plant): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:200']],
            [], ['quantity' => 'số lượng hỏng', 'reason' => 'lý do']);
        try {
            $this->inventory->recordDamage($plant->id, (int) $data['quantity'], $data['reason'], $request->user()->id);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã ghi nhận hư hỏng.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Services\InventoryService;
use Illuminate\Http\Request;

/** Kho: tồn hiện tại, sổ kho từng cây, kiểm kê theo số đếm thực tế, ghi hư hỏng. */
class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $plants = Plant::with('category')
            ->when($request->boolean('low'), fn ($q) => $q->needsReorder())
            ->orderByRaw('stock <= reorder_point DESC')->orderBy('stock')->paginate(20)->withQueryString();

        return view('admin.inventory.index', compact('plants'));
    }

    public function show(Plant $plant)
    {
        $movements = $plant->stockMovements()->with(['orderItem.order', 'goodsReceiptItem.receipt', 'creator'])
            ->latest('id')->paginate(30);

        return view('admin.inventory.show', compact('plant', 'movements'));
    }

    public function count(Request $request, Plant $plant)
    {
        $data = $request->validate(['counted' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:200']],
            [], ['counted' => 'số đếm thực tế', 'reason' => 'ghi chú kiểm kê']);
        try {
            $m = $this->inventory->countTo($plant->id, (int) $data['counted'], $data['reason'], $request->user()->id);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $m ? "Đã điều chỉnh tồn {$m->quantity} (còn {$m->stock_after})." : 'Số đếm khớp tồn kho, không cần điều chỉnh.');
    }

    public function damage(Request $request, Plant $plant)
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

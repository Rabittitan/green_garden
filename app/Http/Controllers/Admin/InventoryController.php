<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlantUnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Plant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Kho: tồn hiện tại, sổ kho từng cây. Hàng lỗi: phiếu báo lỗi (DefectReportController). Kiểm kê: phiếu kiểm kê (StockCountController). */
class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $plants = Plant::with('category')
            ->when($request->boolean('low'), fn ($q) => $q->needsReorder())
            ->orderByRaw('stock <= reorder_point DESC')->orderBy('stock')->paginate(20)->withQueryString();

        return view('admin.inventory.index', compact('plants'));
    }

    public function show(Plant $plant): View
    {
        $movements = $plant->stockMovements()->with(['orderItem.order', 'goodsReceiptItem.receipt', 'defectItem', 'creator'])
            ->latest('id')->paginate(30);
        $unitsOnShelf = $plant->units()->where('status', PlantUnitStatus::InStock->value)->count();

        return view('admin.inventory.show', compact('plant', 'movements', 'unitsOnShelf'));
    }
}

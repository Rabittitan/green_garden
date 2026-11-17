<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Plant;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Phiếu nhập kho: lập (nháp) -> kiểm hàng -> hoàn tất (cộng kho) | hủy (nháp). */
class GoodsReceiptController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(): View
    {
        $receipts = GoodsReceipt::with(['supplier', 'purchaseRequest', 'receiver'])->latest('id')->paginate(20);

        return view('admin.goods-receipts.index', compact('receipts'));
    }

    public function create(Request $request): RedirectResponse|View
    {
        $pr = $request->filled('purchase_request') ? PurchaseRequest::with('items.plant')->findOrFail($request->integer('purchase_request')) : null;
        if ($pr && ! $pr->status->isReceivable()) {
            return redirect()->route('admin.purchase-requests.show', $pr)->with('error', "Yêu cầu {$pr->code} chưa được duyệt hoặc đã đóng.");
        }

        return view('admin.goods-receipts.create', [
            'pr' => $pr,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'plants' => $pr ? collect() : Plant::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array'],
            'lines.*.quantity_received' => ['nullable', 'integer', 'min:0'],
            'lines.*.quantity_rejected' => ['nullable', 'integer', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'integer', 'min:0'],
        ], [], ['supplier_id' => 'nhà cung cấp']);

        $lines = [];
        foreach ($data['lines'] as $plantId => $l) {
            $lines[(int) $plantId] = ['quantity_received' => (int) ($l['quantity_received'] ?? 0),
                'quantity_rejected' => (int) ($l['quantity_rejected'] ?? 0), 'unit_cost' => (int) ($l['unit_cost'] ?? 0)];
        }
        try {
            $receipt = $this->purchases->createReceipt($request->user(), Supplier::findOrFail($data['supplier_id']),
                isset($data['purchase_request_id']) ? PurchaseRequest::findOrFail($data['purchase_request_id']) : null,
                $lines, $data['supplier_invoice_no'] ?? null, $data['note'] ?? null);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.goods-receipts.show', $receipt)->with('success', "Đã lập phiếu {$receipt->code}. Kiểm hàng xong bấm \"Nhập kho\".");
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        return view('admin.goods-receipts.show', ['receipt' => $goodsReceipt->load(['items.plant', 'supplier', 'purchaseRequest', 'receiver'])]);
    }

    public function complete(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        try {
            $this->purchases->completeReceipt($goodsReceipt, $request->user()->id);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã nhập kho, tồn kho và giá vốn đã được cập nhật.');
    }

    public function cancel(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        try {
            $this->purchases->cancelReceipt($goodsReceipt);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã hủy phiếu nhập.');
    }
}

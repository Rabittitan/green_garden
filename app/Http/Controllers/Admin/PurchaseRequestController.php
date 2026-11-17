<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseRequestStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Yêu cầu nhập hàng: nhân viên lập / gửi duyệt; quản trị (khác người lập) duyệt / từ chối. */
class PurchaseRequestController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(PurchaseRequestStatus::class)]])['status'] ?? null;
        $requests = PurchaseRequest::with(['requester', 'supplier'])->withCount('items')
            ->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(20)->withQueryString();

        return view('admin.purchase-requests.index', compact('requests'));
    }

    public function create()
    {
        return view('admin.purchase-requests.form', $this->formData(new PurchaseRequest(['priority' => 'normal'])));
    }

    public function store(Request $request)
    {
        [$items, $meta] = $this->validated($request);

        return $this->run(fn () => $this->purchases->createRequest($request->user(), $items, $meta), 'Đã tạo yêu cầu nhập.', true);
    }

    public function fromLowStock(Request $request)
    {
        try {
            $pr = $this->purchases->draftFromLowStock($request->user());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $pr ? redirect()->route('admin.purchase-requests.show', $pr)->with('success', "Đã lập yêu cầu {$pr->code} từ các cây dưới ngưỡng.")
            : back()->with('warning', 'Không có cây nào dưới ngưỡng tồn kho.');
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        return view('admin.purchase-requests.show', [
            'pr' => $purchaseRequest->load(['items.plant', 'requester', 'approver', 'supplier', 'goodsReceipts.receiver']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        abort_unless($purchaseRequest->status === PurchaseRequestStatus::Draft, 404);

        return view('admin.purchase-requests.form', $this->formData($purchaseRequest->load('items')));
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        [$items, $meta] = $this->validated($request);

        return $this->run(fn () => $this->purchases->updateDraft($purchaseRequest, $items, $meta, $request->user()), 'Đã cập nhật yêu cầu nhập.', true);
    }

    /** Gửi duyệt / trả về nháp / hủy / đóng (chấp nhận nhận thiếu). */
    public function transition(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'to' => ['required', Rule::in(['submitted', 'draft', 'cancelled', 'received'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->run(fn () => $this->purchases->transition($purchaseRequest, PurchaseRequestStatus::from($data['to']), $request->user(), $data['reason'] ?? null),
            'Đã cập nhật trạng thái yêu cầu.');
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate(['approved' => ['array'], 'approved.*' => ['integer', 'min:0']]);

        return $this->run(fn () => $this->purchases->approve($purchaseRequest, $request->user(), array_map('intval', $data['approved'] ?? [])), 'Đã duyệt yêu cầu nhập.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'lý do từ chối']);

        return $this->run(fn () => $this->purchases->transition($purchaseRequest, PurchaseRequestStatus::Rejected, $request->user(), $data['reason']), 'Đã từ chối yêu cầu nhập.');
    }

    public function order(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate(['supplier_id' => ['required', 'exists:suppliers,id']], [], ['supplier_id' => 'nhà cung cấp']);

        return $this->run(fn () => $this->purchases->markOrdered($purchaseRequest, Supplier::findOrFail($data['supplier_id']), $request->user()), 'Đã ghi nhận đặt hàng nhà cung cấp.');
    }

    /** @return array{0: array<int, array{quantity:int, estimated_unit_cost:int, note:?string}>, 1: array<string,mixed>} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'priority' => ['required', Rule::in(['low', 'normal', 'urgent'])],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items.*.estimated_unit_cost' => ['nullable', 'integer', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [], ['needed_by' => 'ngày cần hàng']);

        $items = [];
        foreach ($data['items'] as $plantId => $i) {
            if ((int) ($i['quantity'] ?? 0) > 0) {
                $items[(int) $plantId] = ['quantity' => (int) $i['quantity'], 'estimated_unit_cost' => (int) ($i['estimated_unit_cost'] ?? 0), 'note' => $i['note'] ?? null];
            }
        }

        return [$items, collect($data)->only(['reason', 'needed_by', 'priority', 'supplier_id'])->all()];
    }

    private function formData(PurchaseRequest $pr): array
    {
        return [
            'pr' => $pr,
            'plants' => Plant::active()->orderByRaw('stock <= reorder_point DESC')->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'current' => $pr->exists ? $pr->items->keyBy('plant_id') : collect(),
        ];
    }

    private function run(callable $action, string $success, bool $toShow = false)
    {
        try {
            $result = $action();
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return $toShow && $result instanceof PurchaseRequest
            ? redirect()->route('admin.purchase-requests.show', $result)->with('success', $success)
            : back()->with('success', $success);
    }
}

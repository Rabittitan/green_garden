<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\Plant;
use App\Services\FlashSaleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Flash sale: nhân viên tạo + gửi duyệt; quản trị viên duyệt / từ chối / hủy đợt đã duyệt (không tạo). */
class FlashSaleController extends Controller
{
    public function __construct(private FlashSaleService $sales) {}

    public function index(): View
    {
        $sales = FlashSale::with('creator')->withCount('items')
            ->orderByRaw("FIELD(status, 'submitted', 'approved', 'draft', 'rejected', 'cancelled')")->latest('starts_at')->paginate(20);

        return view('admin.flash-sales.index', compact('sales'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.flash-sales.index')->with('error', 'Quản trị viên chỉ duyệt flash sale. Đợt sale do nhân viên tạo.');
        }

        return view('admin.flash-sales.create', ['sale' => new FlashSale, 'plants' => $this->plants(), 'chosen' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $items] = $this->validated($request);
        try {
            $sale = $this->sales->create($request->user(), $data, $items);
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.flash-sales.show', $sale)->with('success', 'Đã lưu nháp. Bấm "Gửi duyệt" để quản trị viên duyệt.');
    }

    public function show(FlashSale $flashSale): View
    {
        return view('admin.flash-sales.show', ['sale' => $flashSale->load(['items.plant', 'creator', 'approver'])]);
    }

    public function edit(FlashSale $flashSale): View|RedirectResponse
    {
        if (! $flashSale->status->isEditable()) {
            return redirect()->route('admin.flash-sales.show', $flashSale)->with('error', 'Chỉ sửa được đợt sale nháp hoặc bị từ chối.');
        }

        return view('admin.flash-sales.edit', ['sale' => $flashSale, 'plants' => $this->plants(),
            'chosen' => $flashSale->items()->get()->keyBy('plant_id')]);
    }

    public function update(Request $request, FlashSale $flashSale): RedirectResponse
    {
        [$data, $items] = $this->validated($request);

        return $this->run(fn () => $this->sales->update($flashSale, $request->user(), $data, $items), $flashSale, 'Đã lưu. Bấm "Gửi duyệt" khi xong.');
    }

    public function submit(Request $request, FlashSale $flashSale): RedirectResponse
    {
        return $this->run(fn () => $this->sales->submit($flashSale, $request->user()), $flashSale, 'Đã gửi quản trị viên duyệt.');
    }

    public function approve(Request $request, FlashSale $flashSale): RedirectResponse
    {
        return $this->run(fn () => $this->sales->approve($flashSale, $request->user()), $flashSale, 'Đã duyệt: giá sale tự áp dụng trong khung giờ.');
    }

    public function reject(Request $request, FlashSale $flashSale): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'lý do']);

        return $this->run(fn () => $this->sales->reject($flashSale, $request->user(), $data['reason']), $flashSale, 'Đã từ chối, nhân viên sẽ sửa và gửi lại.');
    }

    public function cancel(Request $request, FlashSale $flashSale): RedirectResponse
    {
        return $this->run(fn () => $this->sales->cancel($flashSale, $request->user()), $flashSale, 'Đã hủy đợt sale.');
    }

    private function run(callable $fn, FlashSale $sale, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.flash-sales.show', $sale)->with('success', $ok);
    }

    /** @return array{0: array{name: string, starts_at: string, ends_at: string}, 1: array<int, array{sale_price: int, quantity_limit: int|null}>} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'items' => ['required', 'array'],
            'items.*.sale_price' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity_limit' => ['nullable', 'integer', 'min:1'],
        ], [], ['name' => 'tên đợt', 'starts_at' => 'giờ bắt đầu', 'ends_at' => 'giờ kết thúc', 'items' => 'danh sách cây',
            'items.*.sale_price' => 'giá sale', 'items.*.quantity_limit' => 'số suất']);
        $items = [];
        foreach ($data['items'] as $plantId => $row) {
            if (! empty($row['sale_price'])) {
                $items[(int) $plantId] = ['sale_price' => (int) $row['sale_price'], 'quantity_limit' => isset($row['quantity_limit']) ? (int) $row['quantity_limit'] : null];
            }
        }

        return [['name' => $data['name'], 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at']], $items];
    }

    /** @return Collection<int, Plant> */
    private function plants()
    {
        return Plant::active()->orderBy('name')->get(['id', 'name', 'sku', 'price', 'stock', 'cost_price']);
    }
}

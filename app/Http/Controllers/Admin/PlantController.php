<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GoodsReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GoodsReceiptItem;
use App\Models\Plant;
use App\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CRUD cây. Tồn kho / giá vốn KHÔNG sửa ở đây:
 *  - lúc thêm cây: nhập tồn đầu kỳ + giá vốn -> bút toán 'opening'
 *  - sau đó: dùng màn hình Kho (kiểm kê, hư hỏng) hoặc phiếu nhập hàng
 */
class PlantController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function rules(?Plant $plant = null): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('plants', 'sku')->ignore($plant?->id)],
            'price' => ['required', 'integer', 'min:1'],
            'reorder_point' => ['required', 'integer', 'min:0'],
            'light' => ['required', Rule::in(array_keys(Plant::LIGHTS))],
            'element' => ['required', Rule::in(array_keys(Plant::ELEMENTS))],
            'image' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string'],
            'height' => ['nullable', 'string', 'max:60'],
            'pot_size' => ['nullable', 'string', 'max:80'],
            'weight_grams' => ['nullable', 'integer', 'between:1,200000'],
            'growth_rate' => ['nullable', Rule::in(array_keys(Plant::GROWTH_RATES))],
            'watering' => ['nullable', 'string', 'max:100'],
            'growth_info' => ['nullable', 'string', 'max:5000'],
            'care_guide' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function index(Request $request): View
    {
        $plants = Plant::withTrashed()->with('category')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('sku', 'like', '%'.$request->q.'%')))
            ->orderByRaw('deleted_at IS NOT NULL')->latest('id')->paginate(15)->withQueryString();

        return view('admin.plants.index', compact('plants'));
    }

    public function create(): View
    {
        return view('admin.plants.create', ['categories' => Category::orderBy('sort_order')->get()]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'opening_stock' => ['required', 'integer', 'min:0'],
            'cost_price' => ['required', 'integer', 'min:0'],
        ], [], ['opening_stock' => 'tồn đầu kỳ', 'cost_price' => 'giá vốn']);

        DB::transaction(function () use ($data, $request, $inventory) {
            $plant = Plant::create(Arr::except($data, ['opening_stock', 'cost_price']) + [
                'slug' => $this->uniqueSlug($data['name']),
                'sku' => ($data['sku'] ?? null) ?: 'TMP-'.Str::uuid(),
                'is_active' => $request->boolean('is_active', true),
            ]);
            if (empty($data['sku'])) {
                $plant->update(['sku' => 'GL'.str_pad((string) $plant->id, 5, '0', STR_PAD_LEFT)]);
            }
            $inventory->opening($plant, (int) $data['opening_stock'], (int) $data['cost_price'], $request->user()->id);
        });

        return redirect()->route('admin.plants.index')->with('success', 'Đã thêm cây mới!');
    }

    public function edit(Plant $plant): View
    {
        // 5 lần nhập gần nhất của cây (phiếu đã nhập kho): để thấy giá nhập lên xuống thế nào so với giá vốn bình quân
        $recentReceipts = GoodsReceiptItem::with('receipt.supplier')->where('plant_id', $plant->id)
            ->whereHas('receipt', fn ($q) => $q->where('status', GoodsReceiptStatus::Completed->value))
            ->latest('id')->take(5)->get();

        return view('admin.plants.edit', ['plant' => $plant, 'categories' => Category::orderBy('sort_order')->get(), 'recentReceipts' => $recentReceipts]);
    }

    public function update(Request $request, Plant $plant): RedirectResponse
    {
        $data = $request->validate($this->rules($plant));
        $data['sku'] = ($data['sku'] ?? null) ?: $plant->sku;
        $data['is_active'] = $request->boolean('is_active');
        $plant->update($data);                                          // stock / cost_price không có trong $data

        return redirect()->route('admin.plants.index')->with('success', 'Đã cập nhật cây!');
    }

    /** Xóa mềm: đơn cũ, sổ kho, đánh giá vẫn giữ nguyên tham chiếu. */
    public function destroy(Plant $plant): RedirectResponse
    {
        $plant->update(['is_active' => false]);
        $plant->delete();

        return back()->with('success', "Đã ngừng bán và ẩn cây \"{$plant->name}\".");
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'cay';
        $slug = $base;
        for ($i = 2; Plant::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}

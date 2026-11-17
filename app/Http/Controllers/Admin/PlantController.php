<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Plant;
use App\Services\InventoryService;
use Illuminate\Http\Request;
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
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function index(Request $request)
    {
        $plants = Plant::withTrashed()->with('category')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('sku', 'like', '%'.$request->q.'%')))
            ->orderByRaw('deleted_at IS NOT NULL')->latest('id')->paginate(15)->withQueryString();

        return view('admin.plants.index', compact('plants'));
    }

    public function create()
    {
        return view('admin.plants.create', ['categories' => Category::orderBy('sort_order')->get()]);
    }

    public function store(Request $request, InventoryService $inventory)
    {
        $data = $request->validate($this->rules() + [
            'opening_stock' => ['required', 'integer', 'min:0'],
            'cost_price' => ['required', 'integer', 'min:0'],
        ], [], ['opening_stock' => 'tồn đầu kỳ', 'cost_price' => 'giá vốn']);

        DB::transaction(function () use ($data, $request, $inventory) {
            $plant = Plant::create(collect($data)->except(['opening_stock', 'cost_price'])->all() + [
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

    public function edit(Plant $plant)
    {
        return view('admin.plants.edit', ['plant' => $plant, 'categories' => Category::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Plant $plant)
    {
        $data = $request->validate($this->rules($plant));
        $data['sku'] = ($data['sku'] ?? null) ?: $plant->sku;
        $data['is_active'] = $request->boolean('is_active');
        $plant->update($data);                                          // stock / cost_price không có trong $data

        return redirect()->route('admin.plants.index')->with('success', 'Đã cập nhật cây!');
    }

    /** Xóa mềm: đơn cũ, sổ kho, đánh giá vẫn giữ nguyên tham chiếu. */
    public function destroy(Plant $plant)
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

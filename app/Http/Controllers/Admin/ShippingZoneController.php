<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use App\Models\ShippingZoneProvince;
use App\Services\ShippingZoneService;
use App\Support\VnDivisions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Phí vận chuyển theo vùng + cân nặng (chỉ quản trị viên: giá hay biến động). */
class ShippingZoneController extends Controller
{
    public function __construct(private ShippingZoneService $zones) {}

    public function index(): View
    {
        $zones = ShippingZone::with('provinces')->orderBy('sort_order')->orderBy('id')->get();
        $assigned = $zones->flatMap(fn ($z) => $z->provinces->pluck('province'))->all();

        return view('admin.shipping-zones.index', [
            'zones' => $zones,
            'unassigned' => array_values(array_diff(VnDivisions::provinces(), $assigned)),
        ]);
    }

    public function create(): View
    {
        return view('admin.shipping-zones.form', $this->formData(new ShippingZone(['base_weight_grams' => 2000, 'extra_fee_per_kg' => 5000])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->zones->save(null, $this->validated($request), $request->input('provinces', []));

        return redirect()->route('admin.shipping-zones.index')->with('success', 'Đã thêm vùng phí ship.');
    }

    public function edit(ShippingZone $shippingZone): View
    {
        return view('admin.shipping-zones.form', $this->formData($shippingZone->load('provinces')));
    }

    public function update(Request $request, ShippingZone $shippingZone): RedirectResponse
    {
        $this->zones->save($shippingZone, $this->validated($request), $request->input('provinces', []));

        return redirect()->route('admin.shipping-zones.index')->with('success', 'Đã cập nhật vùng phí ship.');
    }

    public function destroy(ShippingZone $shippingZone): RedirectResponse
    {
        $this->zones->delete($shippingZone);

        return redirect()->route('admin.shipping-zones.index')->with('success', 'Đã xóa vùng (các tỉnh của vùng chuyển về vùng mặc định).');
    }

    /** @return array<string, mixed> */
    private function formData(ShippingZone $zone): array
    {
        $taken = ShippingZoneProvince::with('zone')->when($zone->exists, fn ($q) => $q->where('shipping_zone_id', '<>', $zone->id))->get()
            ->mapWithKeys(fn ($p) => [$p->province => $p->zone->name])->all();

        return ['zone' => $zone, 'provinces' => VnDivisions::provinces(), 'taken' => $taken,
            'mine' => $zone->exists ? $zone->provinces->pluck('province')->all() : []];
    }

    /** @return array{name: string, base_fee: int, base_weight_grams: int, extra_fee_per_kg: int, is_default: bool, sort_order: int} */
    private function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'base_fee' => ['required', 'integer', 'min:0', 'max:10000000'],
            'base_weight_kg' => ['required', 'numeric', 'min:0', 'max:200'],
            'extra_fee_per_kg' => ['required', 'integer', 'min:0', 'max:10000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'provinces' => ['nullable', 'array'], 'provinces.*' => ['string', 'max:100'],
        ], [], ['name' => 'tên vùng', 'base_fee' => 'phí cơ bản', 'base_weight_kg' => 'khối lượng tính trong phí cơ bản', 'extra_fee_per_kg' => 'phí mỗi kg thêm']);

        return ['name' => $d['name'], 'base_fee' => (int) $d['base_fee'], 'base_weight_grams' => (int) round($d['base_weight_kg'] * 1000),
            'extra_fee_per_kg' => (int) $d['extra_fee_per_kg'], 'is_default' => $request->boolean('is_default'), 'sort_order' => (int) ($d['sort_order'] ?? 0)];
    }
}

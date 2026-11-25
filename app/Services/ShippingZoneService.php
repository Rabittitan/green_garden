<?php

namespace App\Services;

use App\Models\ShippingZone;
use App\Support\VnDivisions;
use Illuminate\Support\Facades\DB;

/** Quản trị viên cấu hình vùng phí ship: giá + danh sách tỉnh. 1 tỉnh thuộc tối đa 1 vùng (gán sang vùng mới thì rời vùng cũ). */
class ShippingZoneService
{
    /**
     * @param  array{name: string, base_fee: int, base_weight_grams: int, extra_fee_per_kg: int, is_default: bool, sort_order: int}  $data
     * @param  list<string>  $provinces
     */
    public function save(?ShippingZone $zone, array $data, array $provinces): ShippingZone
    {
        $provinces = array_values(array_intersect(VnDivisions::provinces(), $provinces));   // chỉ tên trong danh mục 34 tỉnh

        return DB::transaction(function () use ($zone, $data, $provinces) {
            $zones = ShippingZone::orderBy('id')->lockForUpdate()->get();               // bảng nhỏ: khóa cả bảng, giữ đúng 1 vùng mặc định
            if ($data['is_default']) {
                ShippingZone::where('is_default', true)->when($zone, fn ($q) => $q->whereKeyNot($zone->id))->update(['is_default' => false]);
            } elseif ($zones->isEmpty()) {
                $data['is_default'] = true;                                              // vùng đầu tiên luôn là mặc định
            }
            $zone ??= new ShippingZone;
            $zone->fill($data)->save();
            DB::table('shipping_zone_provinces')->where('shipping_zone_id', $zone->id)->orWhereIn('province', $provinces)->delete();
            foreach ($provinces as $p) {
                $zone->provinces()->create(['province' => $p]);
            }

            return $zone;
        }, 3);
    }

    public function delete(ShippingZone $zone): void
    {
        DB::transaction(function () use ($zone) {
            $wasDefault = $zone->is_default;
            $zone->delete();                                                             // tỉnh của vùng xóa theo khóa ngoại cascade
            if ($wasDefault && ($first = ShippingZone::orderBy('sort_order')->orderBy('id')->first())) {
                $first->update(['is_default' => true]);
            }
        }, 3);
    }
}

<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use App\Services\ShippingZoneService;
use Illuminate\Database\Seeder;

/**
 * Vùng phí ship MẪU để thử (KHÔNG chạy trong DatabaseSeeder, để dữ liệu mẫu / test giữ phí đồng giá):
 *   php artisan db:seed --class=ShippingZoneSeeder
 * Shop thật tự cấu hình ở Admin → Phí vận chuyển.
 */
class ShippingZoneSeeder extends Seeder
{
    public function run(ShippingZoneService $zones): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Không chạy dữ liệu mẫu ở production. Cấu hình vùng ở Admin → Phí vận chuyển.');
        }
        if (ShippingZone::exists()) {
            return;
        }
        $zones->save(null, ['name' => 'Nội thành TP.HCM', 'base_fee' => 20000, 'base_weight_grams' => 2000, 'extra_fee_per_kg' => 3000, 'is_default' => false, 'sort_order' => 1],
            ['Thành phố Hồ Chí Minh']);
        $zones->save(null, ['name' => 'Miền Nam lân cận', 'base_fee' => 30000, 'base_weight_grams' => 2000, 'extra_fee_per_kg' => 5000, 'is_default' => false, 'sort_order' => 2],
            ['Đồng Nai', 'Tây Ninh', 'Đồng Tháp', 'Vĩnh Long', 'An Giang', 'Thành phố Cần Thơ', 'Cà Mau', 'Lâm Đồng']);
        $zones->save(null, ['name' => 'Các tỉnh còn lại', 'base_fee' => 45000, 'base_weight_grams' => 2000, 'extra_fee_per_kg' => 8000, 'is_default' => true, 'sort_order' => 3], []);
    }
}

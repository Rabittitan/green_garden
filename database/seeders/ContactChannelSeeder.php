<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Kênh liên hệ MẪU cho bong bóng mạng xã hội. Shop thật nhập link ở Admin → Kênh liên hệ. */
class ContactChannelSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Không chạy dữ liệu mẫu ở production. Nhập link ở Admin → Kênh liên hệ.');
        }
        $now = now();
        DB::table('contact_channels')->insert([
            ['channel' => 'zalo', 'url' => 'https://zalo.me/0900000001', 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['channel' => 'facebook', 'url' => 'https://www.facebook.com/greengarden.demo', 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['channel' => 'tiktok', 'url' => 'https://www.tiktok.com/@greengarden.demo', 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}

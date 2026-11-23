<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** 5 slide MẪU cho trang chủ (ảnh Unsplash như dữ liệu mẫu khác). Shop thật quản lý ở Admin → Slide trang chủ. */
class HomeSlideSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Không chạy dữ liệu mẫu ở production. Thêm slide ở Admin → Slide trang chủ.');
        }
        $img = fn ($id) => "https://images.unsplash.com/photo-$id?w=1600&q=80";
        $now = now();
        $slides = [
            ['Cây Cảnh Phong Thủy & Nội Thất', 'Chọn cây hợp mệnh - Đón tài lộc, thanh lọc không khí', $img('1466692476868-aef1dfb1e735'), '/cay-canh', 'Khám phá ngay'],
            ['Cây hợp mệnh Kim', 'Kim Tiền, Kim Ngân, Ngọc Ngân cho góc làm việc', $img('1593691509543-c55fb32d8de5'), '/cay-canh?element=kim', 'Xem cây mệnh Kim'],
            ['Miễn phí giao hàng', 'Cho đơn từ 500.000đ trên toàn quốc', $img('1485955900006-10f4d324d411'), '/cay-canh', 'Mua ngay'],
            ['Sen đá & xương rồng', 'Nhỏ xinh, dễ chăm, hợp bàn làm việc', $img('1459411552884-841db9b3cc2a'), '/cay-canh?category=3', 'Xem bộ sưu tập'],
            ['Bí quyết chăm cây', 'Mẹo tưới nước, ánh sáng, thay chậu từ GreenGarden', $img('1614594975525-e45190c55d0b'), '/tin-tuc', 'Đọc bài viết'],
        ];
        foreach ($slides as $i => [$title, $subtitle, $image, $link, $button]) {
            DB::table('home_slides')->insert(['title' => $title, 'subtitle' => $subtitle, 'image' => $image, 'link_url' => $link,
                'button_label' => $button, 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}

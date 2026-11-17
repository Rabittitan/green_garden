<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dữ liệu mẫu. Ghi thẳng bằng DB (không qua Model) để seeder chỉ phụ thuộc schema.
 * Tồn đầu kỳ của mỗi cây = 1 bút toán 'opening' trong sổ kho, nên plants.stock khớp SUM(stock_movements).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $baseTier = DB::table('membership_tiers')->where('rank', 0)->value('id');

        // ===== 1. Tài khoản mẫu (hạng cơ bản + lịch sử hạng 'initial') =====
        $accounts = [
            ['Quản Trị Viên', 'admin@greenlife.test', 'admin', '0900000001'],
            ['Nguyễn Văn Khách', 'khach@greenlife.test', 'customer', '0900000002'],
        ];
        foreach ($accounts as [$name, $email, $role, $phone]) {
            $userId = DB::table('users')->insertGetId([
                'name' => $name, 'email' => $email, 'password' => Hash::make('123456'), 'role' => $role,
                'phone' => $phone, 'membership_tier_id' => $baseTier, 'tier_achieved_at' => $now,
                'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('membership_tier_histories')->insert([
                'user_id' => $userId, 'to_tier_id' => $baseTier, 'reason' => 'initial', 'created_at' => $now,
            ]);
        }
        $customerId = DB::table('users')->where('email', 'khach@greenlife.test')->value('id');
        DB::table('user_addresses')->insert([
            'user_id' => $customerId, 'recipient_name' => 'Nguyễn Văn Khách', 'phone' => '0900000002',
            'province' => 'TP. Hồ Chí Minh', 'district' => 'Quận 1', 'ward' => 'Phường Bến Nghé',
            'address_line' => '12 Nguyễn Huệ', 'is_default' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // ===== 2. Danh mục (id tự tăng 1->5) =====
        $categories = ['Cây phong thủy', 'Cây để bàn văn phòng', 'Sen đá - Xương rồng', 'Cây nội thất lá đẹp', 'Cây ban công - sân vườn'];
        foreach ($categories as $i => $name) {
            DB::table('categories')->insert([
                'name' => $name, 'slug' => Str::slug($name), 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Hàm nhỏ tạo link ảnh Unsplash cho gọn
        $img = fn ($id) => "https://images.unsplash.com/photo-$id?w=600&q=80";

        // ===== 3. 15 cây cảnh: [category_id, tên, giá, tồn kho, ánh sáng, mệnh, ảnh, mô tả] =====
        $plants = [
            [1, 'Cây Kim Tiền', 250000, 30, 'ua_bong', 'kim', $img('1593691509543-c55fb32d8de5'), 'Biểu tượng của tài lộc, lá xanh bóng, dễ chăm, rất hợp đặt phòng khách và quầy thu ngân.'],
            [1, 'Cây Kim Ngân', 320000, 25, 'ua_sang', 'kim', $img('1485955900006-10f4d324d411'), 'Thân bện 3-5 nhánh mang ý nghĩa tiền bạc đầy nhà, hợp làm quà tân gia.'],
            [2, 'Cây Lưỡi Hổ', 120000, 50, 'ua_bong', 'tho', $img('1597305877032-0668b3c6413a'), 'Lọc không khí tốt, chịu hạn giỏi, thích hợp người bận rộn hay quên tưới nước.'],
            [4, 'Cây Trầu Bà Đế Vương', 280000, 20, 'ua_bong', 'moc', $img('1614594975525-e45190c55d0b'), 'Lá to bản màu xanh đậm sang trọng, tượng trưng cho quyền lực và sự thịnh vượng.'],
            [3, 'Sen Đá Nâu', 45000, 80, 'ua_sang', 'tho', $img('1459411552884-841db9b3cc2a'), 'Nhỏ xinh, mọng nước, đặt bàn làm việc rất dễ thương, ít cần tưới.'],
            [4, 'Cây Bàng Singapore', 450000, 15, 'ua_sang', 'moc', $img('1466692476868-aef1dfb1e735'), 'Lá tròn bóng, dáng cây thanh lịch, là điểm nhấn nội thất được ưa chuộng nhất hiện nay.'],
            [1, 'Cây Phát Tài', 350000, 22, 'ua_bong', 'moc', $img('1512428813834-c702c7702b78'), 'Mang ý nghĩa làm ăn phát đạt, thân gỗ chắc, thường đặt trước cửa văn phòng.'],
            [3, 'Xương Rồng Tai Thỏ', 60000, 40, 'ua_sang', 'hoa', $img('1509423350716-97f9360b4e09'), 'Hình dáng ngộ nghĩnh như tai thỏ, chịu nắng tốt, hợp mệnh Hỏa.'],
            [5, 'Hoa Giấy', 180000, 18, 'ua_sang', 'hoa', $img('1416879595882-3373a0480b5b'), 'Hoa nở rực rỡ quanh năm, trồng ban công cho không gian thêm sinh động.'],
            [1, 'Cây Thiết Mộc Lan', 400000, 12, 'ua_bong', 'hoa', $img('1530968464165-7a1861cbaf9f'), 'Thân cứng cáp, tượng trưng cho sự bền vững, vững vàng trong sự nghiệp.'],
            [2, 'Trúc Phú Quý Thủy Sinh', 150000, 35, 'ua_bong', 'thuy', $img('1545241047-6083a3684587'), 'Trồng trong bình nước, không cần đất, mang ý nghĩa phú quý bình an.'],
            [2, 'Cây Lan Ý', 220000, 28, 'ua_bong', 'thuy', $img('1501004318641-b39e6451bec6'), 'Hoa trắng thanh khiết, lọc không khí tốt, hợp đặt bàn làm việc hoặc phòng ngủ.'],
            [4, 'Cây Cau Tiểu Trâm', 190000, 24, 'ua_bong', 'moc', $img('1520412099551-62b6bafeb5bb'), 'Dáng thanh mảnh như dừa nhỏ, mang không khí nhiệt đới vào ngôi nhà.'],
            [5, 'Cây Dương Xỉ', 130000, 30, 'ua_bong', 'thuy', $img('1587334274328-64186a80aeee'), 'Lá xẻ mềm mại, ưa ẩm, rất hợp treo ban công thoáng mát.'],
            [2, 'Cây Ngọc Ngân', 170000, 26, 'ua_bong', 'kim', $img('1463320898484-cdee8141c787'), 'Lá có đốm trắng bạc, hợp mệnh Kim, tạo điểm nhấn cho góc làm việc.'],
            [1, 'Dang Cao Tri', 10090000, 1, 'ua_bong', 'kim', $img('1463320898484-cdee8141c787'), 'Lá có đốm trắng bạc, hợp mệnh Kim, tạo điểm nhấn cho góc làm việc.'],
        ];

        foreach ($plants as $i => $p) {
            $sku = 'GL'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
            $costPrice = intdiv($p[2] * 6, 10);                          // giá vốn mẫu = 60% giá bán
            $plantId = DB::table('plants')->insertGetId([
                'category_id' => $p[0], 'sku' => $sku, 'name' => $p[1], 'slug' => Str::slug($p[1]).'-'.($i + 1),
                'price' => $p[2], 'cost_price' => $costPrice, 'stock' => $p[3],
                'light' => $p[4], 'element' => $p[5], 'image' => $p[6], 'description' => $p[7],
                'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($p[3] > 0) {
                DB::table('stock_movements')->insert([
                    'plant_id' => $plantId, 'type' => 'opening', 'quantity' => $p[3], 'stock_after' => $p[3],
                    'unit_cost' => $costPrice, 'note' => 'Tồn đầu kỳ', 'created_at' => $now,
                ]);
            }
        }

        // ===== 4. Mã giảm giá mẫu =====
        $silverTier = DB::table('membership_tiers')->where('code', 'silver')->value('id');
        DB::table('coupons')->insert([
            ['code' => 'CHAOBAN', 'name' => 'Giảm 50.000đ đơn đầu tiên', 'type' => 'fixed', 'value' => 50000,
                'max_discount_amount' => null, 'min_order_amount' => 300000, 'min_tier_id' => null, 'combinable_with_tier' => true,
                'first_order_only' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'GIAM10', 'name' => 'Giảm 10% tối đa 100.000đ cho hạng Bạc trở lên', 'type' => 'percent', 'value' => 10,
                'max_discount_amount' => 100000, 'min_order_amount' => 0, 'min_tier_id' => $silverTier, 'combinable_with_tier' => false,
                'first_order_only' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'FREESHIP', 'name' => 'Miễn phí vận chuyển', 'type' => 'free_shipping', 'value' => 0,
                'max_discount_amount' => null, 'min_order_amount' => 150000, 'min_tier_id' => null, 'combinable_with_tier' => true,
                'first_order_only' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ===== 5. Bài viết =====
        $this->call(ArticleSeeder::class);
    }
}

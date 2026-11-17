<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        // Hàm nhỏ tạo link ảnh Unsplash cho gọn
        $img = fn($id) => "https://images.unsplash.com/photo-$id?w=800&q=80";

        $articles = [
            [
                'Cách tưới nước đúng cho cây cảnh trong nhà',
                'Tưới nhiều không phải lúc nào cũng tốt. Học cách kiểm tra độ ẩm đất trước khi tưới.',
                "Nguyên tắc chung là chỉ tưới khi lớp đất bề mặt đã khô. Bạn có thể dùng ngón tay ấn xuống đất khoảng 2 cm, nếu còn ẩm thì chưa cần tưới.\n\nTưới đến khi nước chảy ra lỗ thoát đáy chậu, sau đó đổ bỏ nước thừa ở đĩa lót để rễ không bị úng. Mùa nóng có thể tưới thường xuyên hơn, mùa mát nên giảm bớt.",
                $img('1485955900006-10f4d324d411'),
            ],
            [
                'Cây Kim Tiền: chăm sao cho lá xanh bóng',
                'Kim Tiền dễ sống nhưng vẫn cần đúng ánh sáng và lượng nước để lá luôn xanh.',
                "Kim Tiền ưa bóng nên đặt nơi có ánh sáng gián tiếp, tránh nắng gắt chiếu thẳng làm cháy lá. Tưới khi đất đã khô, mỗi tuần khoảng 1 lần là đủ.\n\nLau lá bằng khăn ẩm định kỳ để lá bóng và cây quang hợp tốt hơn. Nếu lá vàng nhiều, hãy kiểm tra xem cây có bị tưới quá tay không.",
                $img('1593691509543-c55fb32d8de5'),
            ],
            [
                'Sen đá cần nắng và đất thoát nước tốt',
                'Bí quyết giữ sen đá mọng nước, không bị thối rễ: ánh sáng đủ và tưới ít.',
                "Sen đá thích nắng nên đặt ở nơi có nhiều ánh sáng, nếu thiếu sáng cây sẽ vươn dài và mất dáng. Dùng đất tơi xốp, trộn thêm đá nhỏ hoặc cát để nước thoát nhanh.\n\nChỉ tưới khi đất khô hẳn, tránh để nước đọng ở giữa các lá. Tưới quá nhiều là nguyên nhân phổ biến nhất khiến sen đá chết.",
                $img('1459411552884-841db9b3cc2a'),
            ],
            [
                'Lá vàng và lá rụng: cây đang muốn nói gì?',
                'Màu lá thay đổi là tín hiệu sớm cho biết cây đang thiếu hay thừa điều gì.',
                "Lá vàng ở gốc thường do tưới quá nhiều hoặc đất bí. Lá khô đầu mép có thể do không khí quá khô hoặc thiếu nước. Lá cháy nâu từng mảng thường do nắng gắt chiếu trực tiếp.\n\nHãy thay đổi từng yếu tố một (nước, ánh sáng, vị trí) và theo dõi vài tuần để biết nguyên nhân chính xác.",
                $img('1614594975525-e45190c55d0b'),
            ],
            [
                'Khi nào cần thay chậu và bón phân cho cây?',
                'Rễ chen ra lỗ đáy chậu là dấu hiệu cây cần chậu mới rộng hơn.',
                "Thay chậu khi rễ mọc ra khỏi lỗ thoát nước hoặc cây lớn nhanh nhưng đất cạn dần. Chọn chậu lớn hơn chậu cũ khoảng 2 đến 4 cm đường kính, dùng đất mới tơi xốp.\n\nBón phân loãng vào mùa cây phát triển, mỗi lần cách nhau vài tuần. Không bón khi cây đang yếu hoặc vừa mới thay chậu.",
                $img('1530968464165-7a1861cbaf9f'),
            ],
            [
                'Chọn cây hợp mệnh: nên bắt đầu từ đâu?',
                'Gợi ý chọn cây theo mệnh phong thủy để vừa đẹp mắt vừa mang ý nghĩa tốt lành.',
                "Theo quan niệm phong thủy, mỗi mệnh (Kim, Mộc, Thủy, Hỏa, Thổ) hợp với những màu sắc và loại cây khác nhau. Bạn có thể dùng bộ lọc mệnh trên cửa hàng để xem nhanh các cây phù hợp.\n\nĐây là gợi ý mang tính tham khảo. Quan trọng nhất vẫn là chọn cây hợp với ánh sáng và không gian nhà bạn để cây phát triển tốt.",
                $img('1512428813834-c702c7702b78'),
            ],
        ];

        foreach ($articles as $a) {
            Article::create([
                'title' => $a[0], 'summary' => $a[1], 'content' => $a[2], 'image' => $a[3],
            ]);
        }
    }
}
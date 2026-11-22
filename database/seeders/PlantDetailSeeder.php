<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Thông tin chi tiết MẪU cho cây demo (K8): kích thước, chậu, tốc độ lớn, tưới nước, sinh trưởng, chăm sóc.
 * Là nội dung tham khảo chung cho dữ liệu thử; shop thật nhập/sửa ở Quản trị → Cây cảnh.
 * Khớp theo tên cây do DatabaseSeeder tạo; cây không có trong danh sách để trống (trang cây tự ẩn mục).
 */
class PlantDetailSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}> tên => [cao, chậu, tốc độ, tưới, sinh trưởng, chăm sóc] */
    private const DETAILS = [
        'Cây Kim Tiền' => ['Cao 50–60 cm', 'Chậu sứ trắng Ø20 cm', 'slow', '1 lần/tuần',
            "Thân củ dưới đất trữ nước nên cây chịu hạn tốt. Mỗi năm ra thêm vài nhánh lá mới từ gốc, lá non cuộn tròn rồi xòe dần.\nSau 1–2 năm củ đầy chậu thì nên thay chậu rộng hơn.",
            "Đặt nơi có ánh sáng tán xạ, tránh nắng gắt chiếu thẳng làm cháy lá\nĐất thoát nước tốt, trộn thêm xơ dừa hoặc trấu hun\nChỉ tưới khi đất khô khoảng 2/3 chậu; úng nước dễ thối củ\nBón phân tan chậm 2–3 tháng/lần vào mùa xuân hè"],
        'Cây Kim Ngân' => ['Cao 60–80 cm, thân bện 3 nhánh', 'Chậu sứ Ø22 cm', 'medium', '1–2 lần/tuần',
            "Thân gỗ phình to ở gốc để trữ nước, lá xòe hình bàn tay. Cây ra lá quanh năm, mạnh nhất vào mùa mưa.\nCắt tỉa ngọn để giữ dáng gọn và kích thích ra chồi mới.",
            "Ưa sáng nhẹ, đặt gần cửa sổ\nĐể đất khô bề mặt rồi mới tưới, không để nước đọng đĩa lót\nLau lá bằng khăn ẩm để lá bóng và quang hợp tốt\nBón NPK pha loãng 1 lần/tháng khi cây đang ra lá"],
        'Cây Lưỡi Hổ' => ['Cao 40–50 cm', 'Chậu xi măng Ø18 cm', 'slow', '2–3 tuần/lần',
            "Mọc thành bụi từ thân rễ, mỗi năm đẻ thêm vài cây con quanh gốc. Lá dày, mọng nước, sống bền nhiều năm.\nCó thể tách cây con để trồng chậu mới khi bụi quá dày.",
            "Chịu được bóng râm lẫn nắng nhẹ\nTưới rất ít, mùa lạnh có thể 3–4 tuần mới tưới một lần\nĐất pha cát, thoát nước nhanh\nTránh tưới vào nõn lá để không bị thối"],
        'Cây Trầu Bà Đế Vương' => ['Cao 60–70 cm', 'Chậu sứ Ø25 cm', 'medium', '2 lần/tuần',
            "Lá mới mọc từ ngọn, có bẹ màu đỏ hoặc xanh nhạt bao ngoài, lá trưởng thành to bản và bóng.\nCây lớn đều quanh năm trong nhà nếu đủ ánh sáng tán xạ.",
            "Ánh sáng tán xạ, tránh nắng trực tiếp buổi trưa\nGiữ đất hơi ẩm nhưng không sũng nước\nPhun sương quanh lá vào mùa khô\nCắt bỏ lá già vàng sát gốc để cây tập trung nuôi lá mới"],
        'Sen Đá Nâu' => ['Cao 8–10 cm', 'Chậu nhựa Ø7 cm', 'slow', '1 lần/tuần',
            "Lá mọng nước xếp thành hình hoa thị. Đủ nắng lá lên màu nâu đỏ đẹp, thiếu nắng cây vươn dài và nhạt màu.\nCó thể nhân giống bằng cách giâm lá.",
            "Cần ít nhất 4 giờ nắng mỗi ngày\nTưới vào gốc, tránh đọng nước trên lá\nĐất trộn nhiều đá perlite, sỏi nhỏ\nMùa mưa đưa vào nơi có mái che"],
        'Cây Bàng Singapore' => ['Cao 1,0–1,2 m', 'Chậu nhựa giả đá Ø30 cm', 'medium', '2 lần/tuần',
            "Thân thẳng, lá to tròn mọc so le. Cây ra cặp lá mới khá đều nếu đủ sáng; thiếu sáng lá nhỏ dần và rụng lá gốc.\nKhông nên di chuyển cây liên tục vì cây dễ sốc rụng lá.",
            "Đặt nơi sáng nhất trong nhà, gần cửa kính\nTưới khi 3–4 cm đất mặt đã khô\nXoay chậu mỗi tuần để cây không bị nghiêng về phía sáng\nBón phân hữu cơ hoặc NPK 1 lần/tháng mùa xuân hè"],
        'Cây Phát Tài' => ['Cao 1,2–1,5 m', 'Chậu xi măng Ø30 cm', 'slow', '1–2 lần/tuần',
            "Thân gỗ chắc, ra lá thành chùm ở ngọn. Cây sống lâu năm, lớn chậm nhưng bền.\nKhi thân quá cao có thể cắt ngọn, cây sẽ đâm chồi mới bên dưới vết cắt.",
            "Chịu bóng tốt, hợp văn phòng\nĐể đất khô mặt rồi mới tưới\nĐầu lá cháy nâu thường do nước nhiều clo: dùng nước để qua đêm\nLau bụi trên lá định kỳ"],
        'Xương Rồng Tai Thỏ' => ['Cao 15–20 cm', 'Chậu đất nung Ø10 cm', 'slow', '10 ngày/lần',
            "Mọc thành các đốt dẹt hình tai thỏ, đốt mới nhú ra từ mép đốt cũ vào mùa ấm.\nGai mềm dạng lông nhưng dễ dính vào da, cầm cẩn thận.",
            "Cần nhiều nắng, đặt bệ cửa sổ hoặc ban công\nĐất cát pha sỏi, thoát nước thật tốt\nTưới ít, mùa lạnh gần như ngưng tưới\nĐeo găng khi thay chậu"],
        'Hoa Giấy' => ['Cao 60–80 cm', 'Chậu nhựa Ø28 cm', 'fast', 'Hằng ngày khi trời nắng',
            "Thân leo dạng bụi, phát triển nhanh. Hoa thực chất là lá bắc nhiều màu, nở rộ khi cây hơi khô hạn và đủ nắng.\nSau mỗi đợt hoa nên cắt tỉa để cây ra cành và hoa mới.",
            "Trồng ngoài trời, nhiều nắng\nMuốn ra hoa nhiều: giảm tưới vài ngày cho đất khô rồi tưới lại\nBón phân giàu lân (P) trước mùa hoa\nCắt tỉa cành già sau khi hoa tàn"],
        'Cây Thiết Mộc Lan' => ['Cao 1,0–1,3 m', 'Chậu sứ Ø28 cm', 'slow', '1 lần/tuần',
            "Thân gỗ, lá dài có sọc vàng giữa. Cây lớn chậm, sống bền nhiều năm trong nhà.\nThỉnh thoảng cây trưởng thành ra chùm hoa nhỏ thơm về đêm.",
            "Ánh sáng tán xạ, chịu bóng khá tốt\nTưới khi đất khô mặt, không để úng\nTránh đặt gần máy lạnh thổi trực tiếp\nCắt lá vàng, khô ở gốc"],
        'Trúc Phú Quý Thủy Sinh' => ['Cao 30–40 cm', 'Bình thủy tinh Ø12 cm', 'medium', 'Thay nước 1 lần/tuần',
            "Trồng thủy sinh, rễ mọc từ đốt ngập nước. Cây ra lá đều quanh năm, thân cao dần theo thời gian.\nNước trong và sạch giúp rễ trắng khỏe.",
            "Thay nước sạch hằng tuần, rửa sạch bình\nGiữ mực nước ngập 2–3 đốt gốc\nĐặt nơi sáng nhẹ, tránh nắng làm nước rêu xanh\nCó thể thêm vài giọt dinh dưỡng thủy sinh mỗi tháng"],
        'Cây Lan Ý' => ['Cao 35–45 cm', 'Chậu sứ Ø16 cm', 'medium', '2–3 lần/tuần',
            "Mọc thành bụi, lá xanh đậm. Hoa trắng có mo bao ngoài, nở nhiều đợt trong năm khi đủ sáng.\nLá rũ xuống là dấu hiệu cây đang thiếu nước.",
            "Ưa bóng mát, ánh sáng tán xạ\nGiữ đất luôn hơi ẩm\nPhun sương khi không khí khô\nCắt cuống hoa đã tàn sát gốc"],
        'Cây Cau Tiểu Trâm' => ['Cao 50–60 cm', 'Chậu sứ Ø20 cm', 'slow', '2 lần/tuần',
            "Dáng như cây cau thu nhỏ, mọc thành bụi nhiều thân. Lá mới mọc từ giữa ngọn, lớn chậm nhưng bền.\nPhù hợp trồng trong nhà nhiều năm.",
            "Chịu bóng tốt, tránh nắng gắt\nGiữ đất hơi ẩm, không để khô kiệt\nPhun sương lá vào mùa hanh khô để tránh nhện đỏ\nBón phân loãng mỗi tháng mùa sinh trưởng"],
        'Cây Dương Xỉ' => ['Tán rộng 40–50 cm', 'Chậu treo Ø20 cm', 'fast', 'Hằng ngày mùa khô',
            "Không ra hoa, sinh sản bằng bào tử ở mặt dưới lá. Lá non cuộn tròn rồi duỗi ra, cây lên tán nhanh khi đủ ẩm.\nTán dày dần, có thể tách bụi khi chậu chật.",
            "Ưa ẩm cao và bóng râm\nKhông để đất khô hoàn toàn\nTreo nơi thoáng gió nhẹ, tránh gió lùa mạnh\nCắt bớt lá khô ở gốc để tán thông thoáng"],
        'Cây Ngọc Ngân' => ['Cao 30–40 cm', 'Chậu sứ Ø16 cm', 'medium', '2 lần/tuần',
            "Lá có đốm và vệt trắng bạc, lá mới nhú từ ngọn. Cây đẻ nhánh ở gốc tạo thành bụi.\nThiếu sáng lâu ngày lá sẽ nhạt màu đốm.",
            "Ánh sáng tán xạ, tránh nắng trực tiếp\nTưới khi đất mặt se khô\nNhựa cây có thể gây ngứa: rửa tay sau khi cắt tỉa, để xa tầm với trẻ nhỏ và thú cưng\nBón phân loãng 1 lần/tháng"],
    ];

    public function run(): void
    {
        // Nội dung mẫu, ghi đè theo tên cây: không chạy ở production (shop thật tự nhập trong trang quản trị)
        if (app()->isProduction()) {
            throw new \RuntimeException('Không chạy dữ liệu mẫu ở production. Nhập thông tin chi tiết cây ở Quản trị → Cây cảnh.');
        }
        foreach (self::DETAILS as $name => [$height, $pot, $rate, $water, $growth, $care]) {
            DB::table('plants')->where('name', $name)->update([
                'height' => $height, 'pot_size' => $pot, 'growth_rate' => $rate, 'watering' => $water,
                'growth_info' => $growth, 'care_guide' => $care,
            ]);
        }
    }
}

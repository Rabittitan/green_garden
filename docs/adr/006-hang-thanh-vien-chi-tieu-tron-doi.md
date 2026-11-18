# ADR-006: Hạng thành viên theo chi tiêu trọn đời, chỉ tính đơn đã qua hạn đổi trả, chỉ nâng

**Status:** Accepted · **Date:** 2026-11-17 (từ yêu cầu chủ dự án ở G1, bổ sung B1/B3 ở H1) · **Deciders:** chủ dự án

## Context
Yêu cầu của chủ dự án: các hạng là cố định và **dựa trên tổng giá trị đơn đã mua**. Có ba vấn đề cần giải quyết:
1. Khách có thể mua để lên hạng rồi trả hàng.
2. Admin cần hạ hạng được khách gian lận.
3. Đổi ngưỡng hạng thì khách hiện có phải được tính lại.

## Decision
- 4 hạng: Thành viên 0 → Bạc 3 triệu → Vàng 10 triệu → Kim cương 30 triệu. Mỗi hạng có % giảm, trần giảm mỗi đơn và ngưỡng miễn ship riêng.
- `lifetime_spend` = tiền thực trả (đã trừ hoàn tiền, không tính phí ship). Chỉ cộng **sau 7 ngày kể từ khi giao** (`membership:record-spend` chạy mỗi giờ), lúc đơn đã hết hạn đổi trả.
- **Chỉ tự nâng hạng, không tự hạ.** Admin hạ hạng thủ công thì hệ thống tôn trọng: chỉ tự nâng lại khi khách vượt sang một ngưỡng **cao hơn** ngưỡng ứng với mốc chi tiêu lúc bị hạ (sửa lỗi B1).
- Đổi ngưỡng hạng thì chạy ngay `membership:recalculate-all` (sửa lỗi B3).

## Options Considered

### A: Cộng chi tiêu ngay khi đơn hoàn thành, trả hàng thì trừ lại và hạ hạng
**Cons:** khách được lên rồi bị xuống hạng, trải nghiệm tệ; ưu đãi đã hưởng trong lúc đó không thu hồi được.

### B: Hạng theo chi tiêu 12 tháng gần nhất, xét lại định kỳ
**Pros:** khuyến khích mua đều. **Cons:** trái yêu cầu "cố định theo tổng đã mua"; khách phải lo bị rớt hạng.

### C: Trọn đời, chốt sau hạn đổi trả, chỉ nâng, tôn trọng thao tác thủ công (chọn)
| Dimension | Assessment |
|---|---|
| Complexity | Trung bình (logic mốc thủ công) |
| Cost | Một lệnh chạy nền mỗi giờ |
| Scalability | Xử lý theo lô 1000 đơn, mỗi đơn cô lập lỗi riêng (F3) |
| Team familiarity | Cao |

## Trade-off Analysis
C chậm hơn A 7 ngày mới lên hạng, nhưng không bao giờ phải hạ hạng vì trả hàng. Khách hiểu dễ: "mua đủ X thì lên hạng, giữ mãi".

## Consequences
- Dễ hơn: giải thích cho khách; đối soát "tổng chi tiêu = tổng các đơn đã chốt".
- Khó hơn: muốn chuyển sang hạng theo năm thì phải thêm cột `period` và chạy lại dữ liệu lịch sử.
- Lịch sử hạng (`membership_tier_histories`) là bằng chứng kiểm toán, không xóa.

## Action Items
1. [x] Test B1 (hạ thủ công không bị tự nâng lại), B3 (hạ ngưỡng thì khách được nâng ngay)
2. [ ] Nếu đổi chính sách sang hạng theo kỳ: viết ADR mới trạng thái Superseded cho ADR này

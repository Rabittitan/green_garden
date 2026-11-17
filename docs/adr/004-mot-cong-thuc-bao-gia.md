# ADR-004: Một công thức báo giá duy nhất; tiền là số nguyên VND

**Status:** Accepted · **Date:** 2026-11-17 (từ G3) · **Deciders:** chủ dự án

## Context
Giá cuối cùng chịu tác động của giảm giá theo hạng (có trần), mã giảm giá (theo phần trăm, số tiền cố định hoặc miễn ship; áp theo phạm vi; có loại không cộng dồn với hạng) và phí ship (ngưỡng miễn phí phụ thuộc hạng). Nếu trang giỏ hàng tính một kiểu mà lúc đặt hàng tính một kiểu khác, khách sẽ thấy một giá và bị thu một giá khác. Khi trả hàng hoặc hoàn tiền một phần, còn phải biết mỗi dòng hàng đã được giảm bao nhiêu.

## Decision
- **`PricingService::quote()`** là công thức duy nhất, dùng cho cả xem trước lẫn đặt hàng. Lúc đặt hàng, `OrderService` truyền vào các cây và mã giảm giá **đã khóa**; khách gửi lên `expected_total`, nếu giá đã đổi thì từ chối bằng `PriceChangedException`.
- Tiền là **số nguyên VND** (`BIGINT UNSIGNED`), không có số thực ở bất kỳ đâu.
- Giảm giá được phân bổ xuống từng dòng hàng bằng **phương pháp số dư lớn nhất** (`Allocator`): tổng các phần luôn khớp tuyệt đối, và chạy lại cho cùng kết quả.
- Mã không cộng dồn với hạng: hệ thống tính cả hai phương án và **chọn phương án lợi hơn cho khách**. Nếu bằng nhau thì giữ ưu đãi hạng để khách không mất lượt dùng mã. Mã áp vào mà không giảm được đồng nào thì bị từ chối.

## Options Considered

### A: Mỗi màn hình tự tính giá
**Cons:** sớm muộn cũng lệch; lỗi kiểu "giỏ hàng hiển thị 250k nhưng bị thu 280k". Loại.

### B: Tiền dạng `DECIMAL` hoặc float, chia đều rồi làm tròn từng dòng
**Cons:** tổng các dòng sau làm tròn lệch tổng đơn vài đồng; CHECK tổng của ADR-001 sẽ không thỏa.

### C: Một service báo giá, tiền nguyên, phân bổ bằng số dư lớn nhất (chọn)
| Dimension | Assessment |
|---|---|
| Complexity | Trung bình |
| Cost | Không đáng kể |
| Scalability | Chạy trong bộ nhớ, không ghi DB |
| Team familiarity | Cao |

## Trade-off Analysis
Gom về một chỗ thì tính năng giá mới nào cũng phải sửa `PricingService`. Đó lại chính là điều mong muốn, vì một chỗ thì một bộ test. Đổi lại, service này phải thuần (không khóa, không ghi) để dùng được cho xem trước.

## Consequences
- Dễ hơn: hoàn tiền một phần hoặc trả hàng theo dòng có sẵn số giảm của từng dòng; đối soát "giảm giá dòng = giảm giá đơn".
- Khó hơn: các quy tắc khuyến mãi phức tạp (mua X tặng Y, combo) đều phải được thể hiện qua `QuoteLine`.
- Cần xem lại khi có hóa đơn VAT: thuế cũng phải phân bổ theo dòng bằng cùng `Allocator`.

## Action Items
1. [x] Test phân bổ (tổng luôn khớp, tái lập được) và test chọn phương án lợi hơn
2. [ ] Khuyến mãi mới: thêm vào `PricingService`, không tính giá ở controller hay view

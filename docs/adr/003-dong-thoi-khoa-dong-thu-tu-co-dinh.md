# ADR-003: Đồng thời — READ COMMITTED và khóa dòng tường minh theo thứ tự cố định

**Status:** Accepted · **Date:** 2026-11-17 (từ G2, hoàn thiện ở H5) · **Deciders:** chủ dự án

## Context
Có những tình huống tranh chấp có thật: nhiều khách cùng mua cây cuối cùng, khách bấm đặt hàng hai lần, IPN VNPay đến đúng lúc lệnh hủy đơn quá hạn chạy, nhân viên hủy đơn đúng lúc tiền về, hai admin cùng xác nhận hoàn tiền. Mức cô lập mặc định REPEATABLE READ của InnoDB tạo **gap lock**, và đó chính là nguồn deadlock giữa luồng đặt hàng và luồng hủy đơn.

## Decision
- Mức cô lập **READ COMMITTED** (`config/database.php`).
- Tính đúng đắn **không dựa vào snapshot**. Mọi luồng ghi đều khóa dòng bằng `lockForUpdate()` theo **một thứ tự cố định**: `users → orders → plants (id tăng dần) → coupons → payments`. Riêng nhập hàng: `goods_receipts → purchase_requests → plants → purchase_request_items`.
- Lưu ý khóa ngầm: chèn dòng con có khóa ngoại sẽ khóa S dòng cha. Ví dụ admin sửa phạm vi mã (`coupon_plants`) chạm tới `plants`, nên `CouponService::update` khóa `plants` (qua `InventoryService::lockPlants`) **trước** `coupons`. Bản cũ khóa `coupons` trước nên deadlock với checkout (nhóm B, 11/2026).
- Mọi transaction chạy qua `DB::transaction(fn, 3)` để tự thử lại khi gặp deadlock.
- Chống bấm đúp: khóa dòng user, kết hợp `checkout_token` dùng một lần và UNIQUE ở DB.
- Kiểm thử bằng **tiến trình PHP thật** chạy song song (`tests/Concurrency/worker.php`), không mô phỏng.

## Options Considered

### A: REPEATABLE READ mặc định, không khóa tường minh
**Cons:** hai giao dịch cùng đọc "còn 1 cây" rồi cùng bán; còn gap lock thì gây deadlock. Loại.

### B: Khóa lạc quan (cột `version`, thử lại khi xung đột)
| Dimension | Assessment |
|---|---|
| Complexity | Trung bình |
| Scalability | Tốt khi ít xung đột |

**Cons:** đơn nhiều cây phải kiểm version trên nhiều dòng; cây "hot" trong giờ khuyến mãi lại có xung đột cao, phải thử lại nhiều lần và trải nghiệm tệ đi.

### C: Đưa mọi thao tác ghi vào một hàng đợi xử lý tuần tự
**Pros:** không còn đua. **Cons:** đặt hàng thành bất đồng bộ (khách phải chờ), cần thêm hạ tầng; quá nặng so với quy mô shop.

### D: READ COMMITTED kết hợp khóa bi quan theo thứ tự cố định (chọn)
| Dimension | Assessment |
|---|---|
| Complexity | Trung bình (phải giữ đúng kỷ luật thứ tự khóa) |
| Cost | Không tốn thêm hạ tầng |
| Scalability | Đủ cho shop vừa và nhỏ; điểm nghẽn là dòng của cây hot |
| Team familiarity | Trung bình |

## Trade-off Analysis
D cho kết quả đồng bộ và dễ hiểu. Đổi lại, toàn đội phải giữ kỷ luật: luồng mới nào khóa sai thứ tự là có thể deadlock. Rủi ro này được giảm bằng tài liệu ngay trong code, `DB::transaction(..., 3)` và bộ test đua (C3 yêu cầu luồng mới có ghi kho hoặc tiền phải thêm kịch bản đua).

## Consequences
- Dễ hơn: đọc code là biết giao dịch nào thắng; 11 kịch bản đua đều xanh.
- Khó hơn: thêm luồng mới phải tuân thủ thứ tự khóa; không được đọc rồi ghi mà không khóa.
- Cần xem lại khi lưu lượng lớn (flash sale): xem xét tách khóa tồn kho hoặc đặt trước số lượng (reservation) trong Redis.

## Action Items
1. [x] 11 kịch bản đua bằng tiến trình thật trong CI
2. [ ] Mỗi luồng mới có ghi kho hoặc tiền: thêm kịch bản vào `ConcurrencyTest` (tiêu chí C3)

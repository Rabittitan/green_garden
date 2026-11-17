# ADR-002: Tồn kho là sổ cái chỉ ghi thêm; `plants.stock` chỉ là cache

**Status:** Accepted · **Date:** 2026-11-17 (từ G1) · **Deciders:** chủ dự án

## Context
Tồn kho thay đổi từ nhiều luồng: đặt hàng, hủy, hết hạn thanh toán, trả hàng, nhập hàng và kiểm kê. Nếu mỗi luồng tự `UPDATE plants SET stock = ...` thì khi số tồn lệch, không thể biết lệch từ đâu ra. Ở giai đoạn đầu đã gặp lỗi lost update đúng kiểu này.

## Decision
- Bảng **`stock_movements`** chỉ được ghi thêm, không sửa, không xóa. Mỗi dòng có FK thật tới chứng từ gốc (`order_item_id`, `goods_receipt_item_id`) và CHECK ràng buộc dấu theo loại (bán thì âm, nhập thì dương, kiểm kê thì có lý do).
- `plants.stock` là **cache** của `SUM(quantity)`. Chỉ `InventoryService` được phép đổi cột này, bên trong `Plant::writingLedger()`; ghi ở bất kỳ chỗ nào khác sẽ ném lỗi.
- Hằng đêm, `shop:reconcile` kiểm `stock = SUM(sổ)`.

## Options Considered

### A: Chỉ có cột `stock`, cộng trừ trực tiếp
| Dimension | Assessment |
|---|---|
| Complexity | Thấp |
| Cost | Ít dòng dữ liệu |
| Scalability | Tốt |
| Team familiarity | Cao |

**Cons:** không có lịch sử, không đối soát được, không trả lời được câu hỏi "vì sao còn 3 cây"; dễ lost update.

### B: Chỉ có sổ cái, tồn kho tính bằng `SUM` mỗi lần đọc
**Pros:** chỉ có một nguồn sự thật. **Cons:** trang danh sách cây phải `SUM` trên mỗi dòng, chậm dần theo dữ liệu, và vi phạm ngân sách 15 câu SQL (C6). Muốn khóa để chống bán âm thì phải khóa một tập dòng thay vì một dòng.

### C: Sổ cái là nguồn sự thật, `stock` là cache có rào bảo vệ (chọn)
| Dimension | Assessment |
|---|---|
| Complexity | Trung bình |
| Cost | Thêm 1 dòng sổ cho mỗi biến động |
| Scalability | Đọc O(1); sổ tăng tuyến tính, có index theo cây |
| Team familiarity | Trung bình (khái niệm kế toán) |

**Pros:** truy vết được, đối soát được; khóa dòng `plants` để chống bán âm. **Cons:** có hai nơi lưu cùng một thông tin, nên cần rào bảo vệ và đối soát.

## Trade-off Analysis
C giữ được tốc độ đọc của A và khả năng kiểm toán của B. Rủi ro hai nguồn lệch nhau được xử lý ba lớp: rào `writingLedger`, cả hai cùng ghi trong một transaction, và đối soát hằng đêm.

## Consequences
- Dễ hơn: kiểm kê và điều tra lệch kho; làm báo cáo nhập, xuất, tồn.
- Khó hơn: mọi tính năng mới có động đến kho **bắt buộc** đi qua `InventoryService`.
- Không sửa tay `plants.stock`. Lệch thì điều chỉnh bằng chức năng Kiểm kê (ghi bút toán `adjustment`).

## Action Items
1. [x] Rào `Plant::writingLedger()` và test chứng minh ghi ngoài rào bị chặn
2. [x] `shop:reconcile` kiểm tồn = sổ
3. [ ] Khi có nhiều kho: thêm `warehouse_id` vào sổ (sổ cái mở rộng tự nhiên)

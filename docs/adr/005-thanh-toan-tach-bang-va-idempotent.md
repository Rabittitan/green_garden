# ADR-005: Thanh toán — bảng `payments` riêng, trạng thái thanh toán suy ra, IPN idempotent

**Status:** Accepted · **Date:** 2026-11-17 (từ G4) · **Deciders:** chủ dự án

## Context
Mỗi đơn có thể có nhiều lần thanh toán (VNPay thất bại rồi thử lại), có thể hoàn tiền một phần hoặc toàn phần, và có thể nhận tiền **sau khi** đơn đã hủy (IPN đến trễ, khách chuyển khoản muộn). VNPay gửi IPN lặp lại nhiều lần, không theo thứ tự, và ai cũng có thể giả mạo một request IPN.

## Decision
- Bảng **`payments`** lưu mỗi lần thu (`charge`) và mỗi lần hoàn (`refund`) thành một dòng có trạng thái riêng. `orders.paid_amount` và `refunded_amount` là tổng được đối soát, còn `payment_status` được **suy ra** bằng `PaymentStatus::derive(...)` chứ không ai gán tay.
- Cột sinh kết hợp UNIQUE: `pending_charge_key` (tối đa một lần thu đang chờ cho mỗi đơn) và `success_charge_key` (tối đa một lần thu thành công cho mỗi đơn).
- IPN: **ghi log thô trước** (`payment_webhook_logs`), sau đó kiểm chữ ký HMAC-SHA512 và số tiền, khóa đơn, xử lý idempotent (đã xử lý thì chỉ trả mã đã nhận).
- Tiền về khi đơn đã hủy thì **không mở lại đơn**, vì hàng có thể đã bán cho người khác. Hệ thống tạo yêu cầu hoàn tiền và gửi email cho khách (H8).
- Hoàn tiền do admin xác nhận thủ công kèm mã giao dịch của ngân hàng hoặc VNPay. API hoàn tiền tự động để giai đoạn sau.
- Cổng `fake` chỉ có ở môi trường local và testing; ở production, nếu cấu hình thiếu thì dùng `DisabledGateway`.

## Options Considered

### A: Gắn cột trạng thái thanh toán và số tiền thẳng vào `orders`
| Dimension | Assessment |
|---|---|
| Complexity | Thấp |

**Cons:** không lưu được lịch sử thử lại, hoàn tiền nhiều lần hay tiền đến muộn; gán tay `payment_status` rất dễ lệch với thực tế.

### B: Bảng `payments` cộng trạng thái suy ra (chọn)
| Dimension | Assessment |
|---|---|
| Complexity | Trung bình |
| Cost | Thêm 2 bảng (payments, webhook_logs) |
| Scalability | Tốt; thêm cổng thanh toán mới chỉ là thêm một `PaymentGateway` |
| Team familiarity | Trung bình |

### C: Dùng một payment service hoặc nền tảng bên ngoài
**Cons:** ở VN, các cổng như VNPay và MoMo thường phải tích hợp trực tiếp; nền tảng trung gian tăng phí và độ phụ thuộc.

## Trade-off Analysis
B phức tạp hơn A, nhưng mọi ca khó (thử lại, hoàn tiền một phần, tiền đến muộn, IPN lặp) đều có chỗ để lưu và có thể đối soát. Ở A, các ca này thường bị dồn vào cột `note`.

## Consequences
- Dễ hơn: thêm MoMo hoặc ZaloPay; đối soát với sao kê; trả lời khách hỏi "tiền tôi đi đâu" bằng log thô.
- Khó hơn: luồng nào đổi tiền cũng phải đi qua `PaymentService` và `recalc()`.
- Cần xem lại khi có API hoàn tiền tự động: luồng `refund` đi từ pending sang succeeded vẫn giữ nguyên, chỉ thay người xác nhận bằng callback.

## Action Items
1. [x] Ma trận trạng thái × hành động (108 ô) và các kịch bản đua IPN
2. [ ] Thử 3 kịch bản trên VNPay sandbox thật (xem `docs/H7-van-hanh.md` mục 4)

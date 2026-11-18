# ADR-007: Email gửi sau commit, qua hàng đợi DB; lỗi gửi không làm hỏng giao dịch

**Status:** Accepted · **Date:** 2026-11-17 (từ H2, bổ sung mốc ở H8) · **Deciders:** chủ dự án

## Context
Mỗi mốc tiền và đơn hàng cần báo cho khách (C9). Có hai lỗi kinh điển cần tránh:
1. Gửi mail **bên trong** transaction rồi transaction rollback, khách nhận email "đã thanh toán" cho một việc chưa từng xảy ra.
2. SMTP chậm hoặc lỗi làm request đặt hàng chậm theo hoặc báo lỗi 500, dù đơn đã lưu.

## Decision
- `Notifier::send()` đăng ký qua **`DB::afterCommit`**: chỉ gửi khi transaction ngoài cùng commit thành công. Rollback thì không gửi gì.
- Notification là `ShouldQueue` trên queue **`database`** (thử 3 lần, sau 1 phút và 5 phút). Production cần một worker do supervisor quản lý.
- Lỗi khi đẩy vào queue hoặc khi gửi được `report()` rồi bỏ qua, không bao giờ ném ngược vào luồng nghiệp vụ.
- Danh sách mốc: đặt hàng, đã thanh toán, đang giao, giao thành công/đã thu COD, hủy (kèm số tiền sẽ hoàn), tiền đến sau khi đơn đã hủy, đã hoàn tiền, hướng dẫn trả hàng, lên hạng.

## Options Considered

### A: Gửi đồng bộ ngay trong request
**Cons:** có cả hai lỗi kinh điển kể trên. Loại.

### B: Hàng đợi kết hợp afterCommit (chọn)
| Dimension | Assessment |
|---|---|
| Complexity | Thấp (dùng sẵn tính năng của Laravel) |
| Cost | Một tiến trình worker; bảng `jobs` trong cùng DB |
| Scalability | Đủ cho vài nghìn mail mỗi ngày; nếu cần nhiều hơn thì chuyển sang Redis hoặc SQS mà không phải sửa code |
| Team familiarity | Cao |

**Cons:** nếu tiến trình chết đúng giữa lúc commit và lúc đẩy vào queue thì có thể mất một mail (hiếm).

### C: Transactional outbox (ghi sự kiện vào bảng trong cùng transaction, một tiến trình khác đọc ra và gửi)
**Pros:** không bao giờ mất mail. **Cons:** phải tự xây bộ phát và chống gửi trùng; quá nặng khi mail chỉ là thông báo. Nguồn sự thật vẫn là trang đơn hàng, khách luôn xem được trạng thái ở đó.

## Trade-off Analysis
Email ở đây là **thông báo**, không phải nguồn sự thật. Mất một mail trong trường hợp cực hiếm là chấp nhận được, còn gửi sai mail (trường hợp A) thì không. Vì vậy B là điểm cân bằng; C chỉ đáng làm khi thông báo trở thành nghĩa vụ pháp lý, ví dụ hóa đơn điện tử.

## Consequences
- Dễ hơn: thêm mốc mới chỉ cần tạo một class `OrderNotification` và một dòng `Notifier::send`.
- Khó hơn: phải giám sát worker và `queue:failed` (đã có trong `docs/H7-van-hanh.md`).
- Cần xem lại khi có hóa đơn VAT hoặc SMS OTP: cân nhắc dùng outbox (phương án C).

## Action Items
1. [x] Test: rollback thì không gửi; SMTP lỗi thì nghiệp vụ vẫn thành công; mọi email đều render được
2. [ ] Production: giám sát `queue:failed` > 0

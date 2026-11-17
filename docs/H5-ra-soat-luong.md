# H5 — Rà soát toàn diện luồng & logic

Mọi mục dưới đây là **test tự động** (chạy trong CI), không phải kiểm tra tay một lần.

## 1. Ma trận trạng thái đơn × hành động — `tests/Feature/Audit/OrderStateMatrixTest.php`
12 hành động × 9 trạng thái = 108 ô. Ô được phép: chạy xong và `shop:reconcile` toàn ✓.
Ô bị cấm: phải là lỗi nghiệp vụ và KHÔNG để lại tác dụng phụ (trạng thái, tiền, kho, giao dịch, lịch sử).

| Hành động | Được phép ở |
|---|---|
| Xác nhận | Chờ xác nhận |
| Giao | Đã xác nhận |
| Giao thành công | Đang giao |
| Nhân viên hủy | Chờ thanh toán, Chờ xác nhận, Đã xác nhận |
| Khách hủy | Chờ thanh toán, Chờ xác nhận |
| Hệ thống hủy | (chỉ khi quá hạn thanh toán) |
| Chuyển hoàn hàng (nhân viên) | Đang giao, Hoàn thành (trong hạn đổi trả) |
| Yêu cầu trả hàng (khách) | Hoàn thành (trong hạn đổi trả) |
| Hủy yêu cầu trả hàng | Đang hoàn về (đơn đã từng nhận) |
| Nhận hàng hoàn | Đang hoàn về |
| Thanh toán online | Chờ thanh toán |
| Hoàn tiền một phần | Chờ xác nhận, Đã xác nhận, Đang giao, Hoàn thành |

## 2. Phân quyền — `tests/Feature/Audit/RoutePermissionMatrixTest.php`
Tự đọc mọi route `admin.*` từ router (route mới thêm tự được kiểm): khách vãng lai → đăng nhập,
khách → 403, nhân viên → 403 ở route tiền / mã / hạng / duyệt nhập, quản trị → được.
Tài nguyên của khách (đơn, địa chỉ, bình luận): người khác → 403/404.

## 3. Gây lỗi giữa chừng — `tests/Feature/Audit/FailureInjectionTest.php`
Đặt hàng, hủy đơn, IPN, nhập kho, chốt chi tiêu, xác nhận hoàn tiền: nổ ở bước cuối → rollback sạch, chạy lại được.

## 4. Đua song song (tiến trình thật) — `tests/Feature/Concurrency/ConcurrencyTest.php`
Tranh cây cuối · bấm đúp nhiều tab · IPN vs hết hạn · hủy vs đặt mới · mã chứng từ · nhập kho vs đặt hàng ·
gửi đánh giá trùng · **nhân viên hủy vs IPN** · **giao vs hủy** · **2 admin xác nhận hoàn tiền** · **3 admin xác nhận đơn**.

## 5. Trình duyệt 3 vai trò — `tests/Browser/roles.e2e.js`
Khách (COD, VNPay, hủy, đánh giá, hỏi đáp, mã giảm giá) · nhân viên (xử lý đơn, duyệt đánh giá, lập yêu cầu nhập,
nhập kho, không vào được chức năng quản trị) · quản trị (duyệt nhập, hoàn tiền, tạo mã). Kết thúc `shop:reconcile` 8/8 ✓.

## Lỗi tìm được và đã sửa (mỗi lỗi có test đỏ -> xanh)
| # | Mức | Lỗi | Sửa |
|---|---|---|---|
| F1 | Trung bình | Tạo được hoàn tiền khi đơn đang hoàn về (chưa kiểm hàng) → dễ hoàn trùng | Chặn ở service; hoàn tại bước nhận hàng hoàn |
| F2 | Trung bình (bảo mật) | Route admin trả 404 cho id không tồn tại, 403 cho id tồn tại → dò được số đơn / phiếu | Kiểm quyền trước khi nạp model từ URL |
| F3 | Cao | 1 đơn lỗi làm dừng cả lô chốt chi tiêu (job mỗi giờ kẹt mãi) | Xử lý từng đơn / từng khách độc lập, lỗi chỉ ghi log |
| F4 | Thấp | (do sửa F2) nhân viên bị khóa nhận 403 thay vì bị đăng xuất | Kiểm "tài khoản bị khóa" trước kiểm quyền |
| T1 | Hạ tầng test | Test đua commit dữ liệu thật làm bẩn DB của lớp test sau | Đánh dấu dựng lại DB sau lớp test đua |

## 6. Phân tích tĩnh — Larastan level 6 (CI, chặn merge)
Lần chạy đầu trên CI: **498 lỗi** → sau khi sửa: **0 lỗi**.
- ~350 lỗi do model thiếu khai báo kiểu: thêm `@property` sinh từ schema DB thật + casts, relation có kiểu + generics.
- 113 action controller thiếu kiểu trả về; ~70 kiểu mảng / generics.
- Lỗi thật tìm được nhờ phân tích tĩnh:

| # | Lỗi | Sửa |
|---|---|---|
| S1 | `PaymentController` gọi `sign()` trên interface `PaymentGateway` (interface không có hàm này) | Kiểm tra `instanceof VnpayGateway` trước khi ký |
| S2 | User vừa `create()` chưa có `role` / `is_active` mặc định của DB trong bộ nhớ → gọi `isBackOffice()` lỗi | Thêm `$attributes` khớp giá trị mặc định của DB |

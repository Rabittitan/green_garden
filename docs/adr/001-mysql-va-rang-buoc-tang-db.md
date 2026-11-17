# ADR-001: Chỉ hỗ trợ MySQL/MariaDB, ràng buộc nghiệp vụ đặt cả ở tầng DB

**Status:** Accepted · **Date:** 2026-11-17 (ghi nhận quyết định từ giai đoạn G1) · **Deciders:** chủ dự án

## Context
Dữ liệu tiền và kho không được phép sai, kể cả khi có bug ở code, khi ai đó sửa DB bằng tay, hoặc khi hai request chạy đua nhau. Laravel mặc định hướng tới code chạy được trên mọi DB (SQLite cho test, MySQL/Postgres khi chạy thật), nên mọi ràng buộc thường chỉ nằm ở tầng ứng dụng. Môi trường thật của dự án là MySQL.

## Decision
- Chỉ hỗ trợ **MySQL ≥ 8.0.16 hoặc MariaDB ≥ 10.4**. Migration gọi `DbCheck::assertSupportedEngine()` và **dừng hẳn** nếu gặp engine khác hoặc phiên bản cũ hơn, vì MySQL < 8.0.16 đọc được cú pháp CHECK nhưng âm thầm bỏ qua.
- Bất biến nghiệp vụ được viết thành **CHECK constraint**. Ví dụ: `total + member_discount + coupon_discount + shipping_discount = subtotal + shipping_fee`. Đẳng thức này viết theo dạng cộng để không bị tràn số với cột `UNSIGNED`.
- Các quy tắc "tối đa một" dùng **cột sinh STORED kết hợp UNIQUE**. Ví dụ: `success_charge_key` chặn hai giao dịch thu tiền thành công cho cùng một đơn, `default_key` chặn hai địa chỉ mặc định.
- Test chạy trên MySQL thật (`green_life_test`), không dùng SQLite.

## Options Considered

### A: Không phụ thuộc DB, ràng buộc chỉ ở code (mặc định của Laravel)
| Dimension | Assessment |
|---|---|
| Complexity | Thấp |
| Cost | Test nhanh hơn với SQLite in-memory |
| Scalability | Như nhau |
| Team familiarity | Cao |

**Pros:** đổi DB dễ, test nhanh. **Cons:** một bug hoặc một lệnh sửa tay là đủ để làm hỏng dữ liệu tiền và kho mà không ai phát hiện; SQLite cũng không mô phỏng được khóa dòng và các tình huống đua.

### B: MySQL kèm ràng buộc ở tầng DB (chọn)
| Dimension | Assessment |
|---|---|
| Complexity | Trung bình (helper `DbCheck`, cú pháp riêng của MySQL) |
| Cost | Test chậm hơn (~100 giây cho 370 test) |
| Scalability | Như nhau |
| Team familiarity | Trung bình |

**Pros:** DB là lớp chặn cuối cùng; test chạy đúng engine thật nên phát hiện được deadlock, khóa dòng và vi phạm UNIQUE. **Cons:** bị khóa vào MySQL; mọi máy dev đều phải cài MySQL.

### C: PostgreSQL
**Pros:** CHECK, partial index và exclusion constraint mạnh hơn. **Cons:** môi trường của người dùng và hosting phổ biến ở VN là MySQL; đổi DB không đem lại lợi ích nào vượt hơn chi phí chuyển đổi.

## Trade-off Analysis
Dự án đánh đổi tính di động và tốc độ test để lấy **dữ liệu đúng ngay cả khi code sai**. Với một shop bán hàng có tiền thật, sai lệch sổ sách tốn kém hơn nhiều so với vài chục giây CI.

## Consequences
- Dễ hơn: không phải tự chứng minh bất biến ở mọi chỗ ghi, vì DB tự từ chối dữ liệu sai; `shop:reconcile` chỉ còn phải canh những bất biến xuyên bảng.
- Khó hơn: đổi bất biến nào cũng phải viết migration; không chạy được trên SQLite hay hosting chỉ có MySQL 5.7.
- **Cảnh báo:** kết nối phải ép `time_zone=+00:00` (bổ sung ở H7). Nếu bỏ, các cột `DEFAULT CURRENT_TIMESTAMP` sẽ lệch 7 giờ trên máy chủ đặt múi giờ +07:00.

## Action Items
1. [x] `DbCheck` + kiểm tra phiên bản engine trong migration
2. [x] CI chạy service MySQL 8
3. [ ] Khi tạo hosting: kiểm tra `SELECT VERSION()` ≥ 8.0.16 trước khi deploy

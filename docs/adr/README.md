# Quyết định kiến trúc (ADR) — GreenLife Shop

Ghi lại **vì sao** hệ thống được thiết kế như hiện tại. Trước khi đổi một điều dưới đây, hãy đọc ADR tương ứng: phần lớn được bảo vệ bởi test, và đổi ngược thường mở lại đúng lỗi mà quyết định đó đã chặn.

| # | Quyết định | Trạng thái | Test bảo vệ chính |
|---|---|---|---|
| [001](001-mysql-va-rang-buoc-tang-db.md) | Chỉ hỗ trợ MySQL 8 / MariaDB 10.4+, ràng buộc nghiệp vụ đặt cả ở tầng DB | Accepted | `tests/Feature/Database/*` |
| [002](002-ton-kho-la-so-cai.md) | Tồn kho là sổ cái chỉ ghi thêm; `plants.stock` chỉ là cache | Accepted | `shop:reconcile`, `ConcurrencyTest` |
| [003](003-dong-thoi-khoa-dong-thu-tu-co-dinh.md) | Đồng thời: READ COMMITTED + khóa dòng tường minh theo thứ tự cố định | Accepted | `ConcurrencyTest` (11 kịch bản, tiến trình thật) |
| [004](004-mot-cong-thuc-bao-gia.md) | Một công thức báo giá duy nhất; tiền là số nguyên VND | Accepted | `PricingServiceTest`, `AllocatorTest` |
| [005](005-thanh-toan-tach-bang-va-idempotent.md) | Thanh toán: bảng `payments` riêng, trạng thái thanh toán suy ra, IPN idempotent | Accepted | `PaymentServiceTest`, `OrderStateMatrixTest` |
| [006](006-hang-thanh-vien-chi-tieu-tron-doi.md) | Hạng thành viên theo chi tiêu trọn đời, chỉ tính đơn đã qua hạn đổi trả, chỉ nâng | Accepted | `MembershipAndInventoryTest`, `H1BugsTest` |
| [007](007-thong-bao-sau-commit-qua-hang-doi.md) | Email gửi sau commit, qua hàng đợi DB; lỗi gửi không làm hỏng giao dịch | Accepted | `NotificationTest` |

Mẫu cho ADR mới: sao chép một file, đánh số tiếp, trạng thái `Proposed` cho tới khi được duyệt.

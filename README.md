# GreenLife Shop 🌿

Website bán cây cảnh: đặt hàng, thanh toán (COD / VNPay / chuyển khoản), voucher, hạng thành viên, kho có sổ cái, nhập hàng từ nhà cung cấp, đánh giá & hỏi đáp. Laravel 12 + MySQL 8.

[![CI](https://github.com/Rabittitan/green_garden/actions/workflows/ci.yml/badge.svg)](https://github.com/Rabittitan/green_garden/actions/workflows/ci.yml)

## Tính năng chính

| Mảng | Nội dung |
|---|---|
| Bán hàng | Giỏ hàng, báo giá thống nhất (xem trước = lúc đặt), giữ hàng khi chờ thanh toán, tự hủy đơn quá hạn, trả hàng trong 7 ngày |
| Thanh toán | COD, VNPay (chữ ký HMAC-SHA512, IPN idempotent), chuyển khoản (admin xác nhận), hoàn tiền |
| Voucher | Giảm % / số tiền / miễn ship, giới hạn lượt dùng, có hoặc không cộng dồn với ưu đãi hạng (tự chọn phương án lợi hơn cho khách) |
| Hạng thành viên | Thành viên → Bạc (3 triệu) → Vàng (10 triệu) → Kim cương (30 triệu) theo tổng chi tiêu trọn đời; chỉ tính đơn đã qua hạn đổi trả, không tự hạ hạng |
| Kho | Sổ kho chỉ ghi thêm (`stock_movements`), tồn kho luôn = tổng sổ; kiểm kê có lý do; cảnh báo sắp hết |
| Nhập hàng | Nhà cung cấp → đề xuất nhập (duyệt) → phiếu nhập (nhận một phần / nhiều lần) |
| Nội dung | Đánh giá (chỉ người đã nhận cây, có duyệt), hỏi đáp dưới cây / bài viết |
| Quản trị | 2 cấp: nhân viên (đơn, kho, duyệt nội dung) và admin (thêm: hoàn tiền, xác nhận chuyển khoản, voucher, hạng, khách hàng) |

## Chạy ở máy dev

Cần **PHP ≥ 8.4.1** (các gói Symfony 8.1 trong `composer.lock` yêu cầu), Composer 2, MySQL ≥ 8.0.16. Laravel 12.

```bash
composer install
cp .env.example .env && php artisan key:generate
# sửa DB_USERNAME / DB_PASSWORD trong .env, rồi:
mysql -u root -p -e "CREATE DATABASE green_life CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
php artisan serve
```

Mở http://127.0.0.1:8000. Tài khoản mẫu (chỉ có trong dữ liệu mẫu; seeder bị chặn ở production):

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Admin | `admin@greenlife.test` | `123456` |
| Khách | `khach@greenlife.test` | `123456` |

Ở local, VNPay chạy bằng **cổng giả lập** (`PAYMENT_GATEWAY=fake`), không cần tài khoản VNPay. Tác vụ nền (hủy đơn quá hạn, lên hạng) và mail:

```bash
php artisan schedule:work      # cửa sổ 1
php artisan queue:work         # cửa sổ 2 (MAIL_MAILER=log: mail ghi vào storage/logs)
```

## Kiểm thử

```bash
mysql -u root -p -e "CREATE DATABASE green_life_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test               # ~370 test, gồm test tranh chấp bằng nhiều tiến trình thật
vendor/bin/pint --test         # định dạng code
vendor/bin/phpstan analyse     # Larastan level 6
php artisan shop:reconcile     # đối soát dữ liệu (kho, tiền, mã, hạng, đánh giá)
```

Test chạy trên MySQL thật (không SQLite) vì nghiệp vụ dựa vào CHECK constraint, khóa dòng và cột sinh UNIQUE. CI trên GitHub chạy toàn bộ ở mỗi lần push.

## Cấu trúc

```
app/Services/          nghiệp vụ: OrderService, PaymentService, PricingService, InventoryService,
                       MembershipService, PurchaseService, ReviewService, CouponService...
app/Http/Controllers/  mỏng: kiểm tra đầu vào -> gọi service -> trả view
app/Enums/             máy trạng thái đơn, thanh toán, phiếu nhập...
database/migrations/   schema + CHECK constraint (MySQL)
routes/console.php     lệnh artisan + lịch chạy nền
config/shop.php        tham số nghiệp vụ (phí ship, thời gian giữ hàng, hạn đổi trả...)
deploy/                script triển khai, sao lưu, khôi phục, cron, supervisor, nginx
tests/                 Unit, Feature (Services, Http, Audit, Security, Ops, Concurrency), Browser
docs/                  ADR (adr/), rà soát luồng (H5), sổ tay vận hành (H7), nghiệm thu (H8)
```

## Triển khai production

Xem **[docs/H7-van-hanh.md](docs/H7-van-hanh.md)**: checklist `.env`, cấu hình VNPay (IPN URL), cron + queue worker, sao lưu / khôi phục, quy trình deploy & rollback, xử lý sự cố khi đối soát báo lệch.

Tóm tắt:

```bash
php artisan migrate --force
php artisan shop:create-admin chu@shop.vn       # tạo admin (không dùng seeder ở production)
APP_DIR=/var/www/greenlife ./deploy/deploy.sh   # các lần deploy sau
```

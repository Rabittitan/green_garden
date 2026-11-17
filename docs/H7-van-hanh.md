# H7 — Sổ tay vận hành GreenLife Shop

Tài liệu cho người triển khai và trực vận hành. Mọi lệnh chạy trong thư mục code (mặc định `/var/www/greenlife`), bằng user chạy PHP-FPM (`www-data`), **không phải root**.

---

## 1. Yêu cầu máy chủ

| Thành phần | Phiên bản | Ghi chú |
|---|---|---|
| PHP | **≥ 8.4.1** (FPM) | extension: `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `xml`, `curl`, `zip` |
| MySQL | **≥ 8.0.16** (hoặc MariaDB ≥ 10.4) | bắt buộc: schema dùng CHECK constraint + cột sinh có UNIQUE. Migration tự dừng nếu engine thấp hơn |
| Nginx | bất kỳ bản còn hỗ trợ | mẫu: `deploy/nginx-greenlife.conf` |
| Supervisor | | giữ queue worker gửi mail: `deploy/supervisor-greenlife-worker.conf` |
| Cron | | bộ lập lịch Laravel + sao lưu: `deploy/crontab.txt` |
| Composer 2 | | không cần Node/npm (giao diện dùng Bootstrap qua CDN) |

---

## 2. Cài đặt lần đầu

```bash
git clone https://github.com/Rabittitan/green_garden.git /var/www/greenlife
cd /var/www/greenlife
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
```

Sửa `.env` theo **checklist production** (mục 3), rồi:

```bash
mysql -e "CREATE DATABASE green_life CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --force                 # KHÔNG --seed: seeder mẫu tự chặn ở production
php artisan shop:create-admin chu@shop.vn --name="Chủ shop"   # hỏi mật khẩu ẩn, tối thiểu 10 ký tự có chữ+số
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
```

Nhân viên (xử lý đơn, kho, duyệt đánh giá; **không** hoàn tiền / sửa mã / sửa hạng): `php artisan shop:create-admin nv@shop.vn --staff`. Lệnh này cũng dùng để **đặt lại mật khẩu admin** khi quên.

Cài cron, supervisor, nginx theo 3 file mẫu trong `deploy/` (đổi `shop.example.vn` và đường dẫn), rồi `certbot --nginx -d <tên miền>`.

---

## 3. Checklist `.env` production

| Biến | Giá trị | Sai thì sao |
|---|---|---|
| `APP_ENV` | `production` | cổng thanh toán giả lập bị **tắt** tự động khi khác local/testing; cookie phiên chỉ gửi qua HTTPS |
| `APP_DEBUG` | `false` | `true` = trang lỗi lộ code, biến môi trường, mật khẩu DB |
| `APP_URL` | `https://<tên miền>` | link trong email + URL quay về VNPay sai |
| `DB_*` | DB riêng, user riêng chỉ có quyền trên DB đó | |
| `TRUSTED_PROXIES` | IP của nginx/LB (`127.0.0.1` nếu nginx cùng máy) | để trống khi có proxy: **mọi khách chung 1 IP** → giới hạn đăng nhập 20 lần/phút khóa cả site; HTTPS bị coi là HTTP |
| `MAIL_MAILER` + `MAIL_*` | SMTP thật | `log` = khách không nhận mail nào |
| `QUEUE_CONNECTION` | `database` | mail đi qua hàng đợi; cần worker (mục 5) |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `warning` | `single` = 1 file log phình mãi |
| `PAYMENT_GATEWAY` | `vnpay` | để `fake` ở production → tự **tắt** thanh toán online (COD vẫn chạy) |
| `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_URL` | lấy từ VNPay | thiếu → thanh toán online tự ẩn |
| `SHOP_BANK_ACCOUNT_NO` (+ tên/ngân hàng) | số TK thật | trống → ẩn phương thức chuyển khoản |
| `SHOP_RETURN_ADDRESS` | địa chỉ nhận hàng trả | in trong email trả hàng |

Kiểm tra nhanh sau khi sửa `.env`: `php artisan optimize && php artisan about` (mục Environment phải là production, Debug OFF).

---

## 4. Cấu hình VNPay (sandbox → thật)

1. Đăng ký sandbox tại VNPay, nhận `vnp_TmnCode` và `vnp_HashSecret` → điền `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`; `PAYMENT_GATEWAY=vnpay`.
2. Khai báo với VNPay **IPN URL**: `https://<tên miền>/thanh-toan/vnpay/ipn` (GET, phải truy cập được từ internet, không cần đăng nhập).
   *Return URL* không cần khai báo: hệ thống tự gửi trong mỗi giao dịch (`/thanh-toan/vnpay/ket-qua`).
3. Thử trên sandbox bằng thẻ test của VNPay: đơn thành công, khách hủy, để quá 15 phút. Sau mỗi lần xem bảng `payment_webhook_logs` (mọi callback đều được ghi) và chạy `php artisan shop:reconcile`.
4. Lên thật: đổi `VNPAY_URL` sang URL production VNPay cấp, thay mã/khóa thật, cập nhật IPN URL trên cổng VNPay thật.

Đảm bảo của hệ thống: IPN xử lý **idempotent** (VNPay gửi lại nhiều lần không cộng tiền 2 lần), kiểm chữ ký HMAC-SHA512 + số tiền; IPN đến sau khi đơn đã hết hạn/hủy → tiền được ghi nhận và tạo **yêu cầu hoàn tiền** để admin xử lý, không mất dấu.

---

## 5. Tác vụ nền

**Cron** (`deploy/crontab.txt`) — 1 dòng `schedule:run` mỗi phút chạy toàn bộ lịch:

| Lệnh | Khi nào | Việc | Không chạy thì |
|---|---|---|---|
| `orders:expire-unpaid` | mỗi phút | hủy đơn VNPay quá 15 phút / chuyển khoản quá 24h chưa trả, **trả hàng về kho** | hàng bị giữ mãi, khách khác không mua được |
| `membership:record-spend` | mỗi giờ | đơn đã qua 7 ngày đổi trả → cộng chi tiêu, tự lên hạng | khách không được lên hạng |
| `shop:reconcile` | 02:17 hằng ngày | đối soát 8 bất biến kho / tiền / mã / hạng / đánh giá | không phát hiện lệch dữ liệu |
| `payments:prune-webhook-logs` | 03:23 | xóa log callback > 180 ngày | bảng log phình |
| `queue:prune-failed --hours=720` | 03:41 | xóa job lỗi > 30 ngày | |
| `deploy/backup-db.sh` | 01:15 | sao lưu DB (mục 6) | |

Kiểm tra lịch: `php artisan schedule:list`.

**Queue worker** (supervisor) — gửi mail đặt hàng, đổi trạng thái đơn, lên hạng. Mỗi mail thử 3 lần (sau 1 phút, 5 phút).

```bash
sudo supervisorctl status greenlife-worker:*      # phải RUNNING
php artisan queue:failed                          # mail gửi hỏng hẳn
php artisan queue:retry all                       # gửi lại sau khi sửa SMTP
```

Lỗi SMTP **không** làm hỏng đặt hàng/thanh toán: mail chỉ được gửi sau khi giao dịch DB đã commit.

---

## 6. Sao lưu & khôi phục

- `deploy/backup-db.sh`: `mysqldump --single-transaction` (không khóa bảng, site vẫn chạy), nén gzip, kiểm tra file nén, giữ 14 ngày (`KEEP_DAYS`). Thông tin đăng nhập MySQL đặt trong `~/.my.cnf` của user chạy cron (quyền 600), **không** ghi trong script.
- Chép bản sao lưu ra **ngoài máy chủ** (S3/Google Drive/máy khác) — mất máy là mất cả DB lẫn bản sao lưu nếu để cùng chỗ.
- **Diễn tập khôi phục hằng tháng** (sao lưu chưa từng khôi phục thử = chưa có sao lưu):

```bash
./deploy/restore-db.sh /var/backups/greenlife/green_life-YYYYMMDD-HHMMSS.sql.gz green_life_restore_check
DB_DATABASE=green_life_restore_check php artisan shop:reconcile      # phải toàn ✓
mysql -e "DROP DATABASE green_life_restore_check"
```

Khôi phục thật (mất dữ liệu): `php artisan down` → khôi phục vào DB mới → trỏ `DB_DATABASE` sang DB đó → `php artisan optimize` → `shop:reconcile` → `php artisan up`. Đơn/thanh toán phát sinh sau thời điểm sao lưu phải đối chiếu lại với `payment_webhook_logs` và sao kê VNPay/ngân hàng.

---

## 7. Triển khai bản mới

```bash
APP_DIR=/var/www/greenlife BRANCH=main ./deploy/deploy.sh
```

Script: sao lưu DB → bật bảo trì (trang 503 tiếng Việt) → `git merge --ff-only` → `composer install --no-dev` → `migrate --force` → `optimize` → `queue:restart` → `shop:reconcile` → mở lại site. **Lỗi ở bất kỳ bước nào: dừng và giữ site ở chế độ bảo trì** (code mới chạy trên schema dở dang nguy hiểm hơn site tạm đóng).

Chỉ deploy commit có CI xanh (Test + Larastan) trên GitHub.

**Rollback**

1. Code: `git checkout <commit cũ> && composer install --no-dev --optimize-autoloader && php artisan optimize && php artisan queue:restart`.
2. Nếu bản lỗi đã chạy migration: khôi phục bản sao lưu bước 0 của chính lần deploy đó (mục 6). Đơn phát sinh từ lúc deploy đến lúc rollback phải nhập lại thủ công → càng phát hiện sớm càng tốt.
3. `php artisan shop:reconcile` → `php artisan up`.

---

## 8. Giám sát & xử lý sự cố

| Theo dõi | Cách | Ngưỡng báo động |
|---|---|---|
| Site sống + kết nối DB | uptime monitor gọi `GET /up` | khác 200 |
| Đối soát hằng đêm | `storage/logs/laravel-*.log` chứa `Đối soát thất bại` | có bất kỳ dòng nào |
| Mail | `php artisan queue:failed` | > 0 |
| Worker | `supervisorctl status` | không RUNNING |
| Sao lưu | `/var/log/greenlife-backup.log` | thiếu dòng `OK` của hôm nay |
| Đơn chờ thanh toán quá hạn | đối soát mục "Đơn chờ thanh toán quá hạn > 10 phút" | có → cron chết |

**Khi đối soát báo lệch** — không sửa tay số liệu. Đọc id trong log, rồi:

- *Tồn kho lệch sổ kho*: tìm thao tác gây lệch trong `stock_movements` của cây đó; điều chỉnh bằng chức năng **Kiểm kê** trên trang Kho (ghi bút toán `adjustment` có lý do), không `UPDATE plants`.
- *Tiền đã thu/hoàn lệch*: đối chiếu `payments` + `payment_webhook_logs` + sao kê VNPay; báo dev.
- *Điểm đánh giá / lượt hữu ích lệch*: `php artisan reviews:rebuild` (dựng lại điểm sao + lượt hữu ích từ dữ liệu gốc).
- *Tổng chi tiêu khách lệch*: `php artisan membership:recalculate-all` (chỉ nâng hạng, không hạ hạng ai).
- *Đơn chờ thanh toán quá hạn*: cron không chạy → kiểm tra crontab, chạy tay `php artisan orders:expire-unpaid`.

**Khách báo đã trả tiền nhưng đơn vẫn chờ**: xem `payment_webhook_logs` theo mã giao dịch. Không có log → VNPay chưa gọi IPN (kiểm tra IPN URL, firewall, `TRUSTED_PROXIES`). Có log → xem `signature_valid` (0 = sai `VNPAY_HASH_SECRET`), `result_code` (mã VNPay trả) và `ip` (IP thật của VNPay nhờ `TRUSTED_PROXIES`). Chuyển khoản: admin bấm *Xác nhận chuyển khoản* trên trang đơn.

---

## 9. Bảo mật vận hành

- Chỉ thư mục `public/` được nginx phục vụ; `.env` quyền 640, chủ sở hữu là user deploy, nhóm `www-data`.
- Không bao giờ chạy `db:seed` ở production (đã chặn trong code; dữ liệu mẫu có admin mật khẩu `123456`).
- Đổi `APP_KEY` sẽ đăng xuất mọi người và làm hỏng link xác thực email đang chờ — chỉ đổi khi lộ khóa.
- `composer audit` chạy trong CI ở mỗi lần push; có cảnh báo lỗ hổng → cập nhật gói rồi deploy.
- Khóa tài khoản nhân viên nghỉ việc ngay trên trang Khách hàng: phiên đang đăng nhập bị đăng xuất ở request kế tiếp.

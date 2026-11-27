# H7 — Sổ tay vận hành GreenGarden Shop

Tài liệu cho người triển khai và trực vận hành. Mọi lệnh chạy trong thư mục code (mặc định `/var/www/greenlife`), bằng user chạy PHP-FPM (`www-data`), **không phải root**.

---

## 1. Yêu cầu máy chủ

| Thành phần | Phiên bản | Ghi chú |
|---|---|---|
| PHP | **≥ 8.4.1** (FPM) | extension: `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `xml`, `curl`, `zip`, `fileinfo`. `php.ini`: `upload_max_filesize = 8M`, `post_max_size = 10M` (ảnh tin tức tối đa 4MB) |
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
php artisan storage:link                    # public/storage -> storage/app/public (ảnh tin tức, slide). Thiếu link ảnh vẫn hiện
                                            # (Laravel tự phát file) nhưng chậm hơn và không được trình duyệt lưu đệm
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
```

Nhân viên (xử lý đơn, kho, gỡ đánh giá không phù hợp; **không** hoàn tiền / sửa mã / sửa hạng): `php artisan shop:create-admin nv@shop.vn --staff`. Lệnh này cũng dùng để **đặt lại mật khẩu admin** khi quên.

Cài cron, supervisor, nginx theo 3 file mẫu trong `deploy/` (đổi `shop.example.vn` và đường dẫn), rồi `certbot --nginx -d <tên miền>`.

---

## 3. Checklist `.env` production

| Biến | Giá trị | Sai thì sao |
|---|---|---|
| `APP_ENV` | `production` | cổng thanh toán giả lập bị **tắt** tự động khi khác local/testing; cookie phiên chỉ gửi qua HTTPS |
| `APP_DEBUG` | `false` | `true` = trang lỗi lộ code, biến môi trường, mật khẩu DB |
| `APP_URL` | `https://<tên miền>` | link trong email + URL quay về VNPay sai |
| `APP_NAME` / `APP_LOCALE` | `"GreenGarden Shop"` / `vi` | tên người gửi + đầu mail hiện "Laravel"; thông báo lỗi form và mail bằng tiếng Anh |
| `DB_*` | DB riêng, user riêng chỉ có quyền trên DB đó | |
| `TRUSTED_PROXIES` | IP của nginx/LB (`127.0.0.1` nếu nginx cùng máy) | để trống khi có proxy: **mọi khách chung 1 IP** → giới hạn đăng nhập 20 lần/phút khóa cả site; HTTPS bị coi là HTTP |
| `MAIL_MAILER` + `MAIL_*` | SMTP thật | `log` = khách không nhận mail nào |
| `QUEUE_CONNECTION` | `database` | mail đi qua hàng đợi; cần worker (mục 5) |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `warning` | `single` = 1 file log phình mãi |
| `PAYMENT_GATEWAY` | `vnpay` | để `fake` ở production → tự **tắt** thanh toán online (COD vẫn chạy) |
| `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_URL` | lấy từ VNPay | thiếu → thanh toán online tự ẩn |
| `SHOP_BANK_ACCOUNT_NO` (+ tên/ngân hàng) | số TK thật | trống → ẩn phương thức chuyển khoản |
| `SHOP_RETURN_ADDRESS` | địa chỉ nhận hàng trả | in trong email trả hàng |
| (không phải `.env`) Phí ship theo vùng | Admin → Phí vận chuyển: tạo vùng, gán tỉnh, đặt phí cơ bản + phí mỗi kg; nhập khối lượng đóng gói ở form cây | chưa tạo vùng nào = thu đồng giá `SHOP_SHIPPING_FEE` như cũ |
| `SHOP_SELLER_NAME`, `SHOP_SELLER_ADDRESS`, `SHOP_SELLER_PHONE`, `SHOP_SELLER_EMAIL`, `SHOP_SELLER_TAX_CODE` | thông tin người bán thật | in trên **hóa đơn bán lẻ** của đơn hàng; dòng nào để trống thì không in. Đây không phải hóa đơn GTGT |

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
| `shop:reconcile` | 02:17 hằng ngày | đối soát 10 bất biến kho / tiền / mã / hạng / đánh giá / suất flash sale / số chậu trên kệ | không phát hiện lệch dữ liệu |
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
- **Ảnh tin tức tải lên** nằm ở `storage/app/public/articles/` (không có trong bản dump DB): sao lưu thư mục này cùng lịch, ví dụ `tar czf /var/backups/greenlife/uploads-$(date +%F).tgz -C /var/www/greenlife/storage/app public`.
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
| Đối soát hằng đêm | **email** "[Cần xử lý] Đối soát dữ liệu…" gửi tới mọi quản trị viên đang hoạt động (cần worker hàng đợi + SMTP); log `storage/logs/laravel-*.log` chứa `Đối soát thất bại` | có bất kỳ email / dòng nào |
| Mail | `php artisan queue:failed` | > 0 |
| Worker | `supervisorctl status` | không RUNNING |
| Sao lưu | `/var/log/greenlife-backup.log` | thiếu dòng `OK` của hôm nay |
| Đơn chờ thanh toán quá hạn | đối soát mục "Đơn chờ thanh toán quá hạn > 10 phút" | có → cron chết |

**Khi đối soát báo lệch** — không sửa tay số liệu. Đọc id trong email hoặc log, rồi:

- *Tồn kho lệch sổ kho* / *Số chậu trong kho lệch tồn*: tìm thao tác gây lệch trong `stock_movements` và lịch sử chậu (Tra cứu chậu) của cây đó; điều chỉnh bằng **Phiếu kiểm kê** (quét mã, ghi bút toán `adjustment`), không `UPDATE plants` / `plant_units`.
- *Tiền đã thu/hoàn lệch*: đối chiếu `payments` + `payment_webhook_logs` + sao kê VNPay; báo dev.
- *Điểm đánh giá / lượt hữu ích lệch*: `php artisan reviews:rebuild` (dựng lại điểm sao + lượt hữu ích từ dữ liệu gốc).
- *Tổng chi tiêu khách lệch*: `php artisan membership:recalculate-all` (chỉ nâng hạng, không hạ hạng ai).
- *Đơn chờ thanh toán quá hạn*: cron không chạy → kiểm tra crontab, chạy tay `php artisan orders:expire-unpaid`.

**Khách báo đã trả tiền nhưng đơn vẫn chờ**: xem `payment_webhook_logs` theo mã giao dịch. Không có log → VNPay chưa gọi IPN (kiểm tra IPN URL, firewall, `TRUSTED_PROXIES`). Có log → xem `signature_valid` (0 = sai `VNPAY_HASH_SECRET`), `result_code` (mã VNPay trả) và `ip` (IP thật của VNPay nhờ `TRUSTED_PROXIES`). Chuyển khoản: admin bấm *Xác nhận chuyển khoản* trên trang đơn.

**Danh mục tỉnh/xã khi đặt hàng** (`public/data/vn-dvhc-2025.json`): 34 tỉnh/thành và 3.321 xã/phường/đặc khu theo địa giới từ 01/07/2025. Trình duyệt dùng file này để hiện danh sách chọn, server dùng chính file này để kiểm tra. Nguồn dữ liệu ghi trong khóa `_meta`. Khi Nhà nước sắp xếp lại đơn vị hành chính: thay file (giữ định dạng `{"provinces":[{"name","wards":[...]}]}`) và chạy `php artisan test tests/Feature/Http/AddressSelectionTest.php` (sửa số lượng mong đợi trong test). Đơn và địa chỉ cũ không bị đổi; khách có địa chỉ cũ sẽ được nhắc chọn lại khi đặt hàng.

---

## 9. Bảo mật vận hành

- Chỉ thư mục `public/` được nginx phục vụ; `.env` quyền 640, chủ sở hữu là user deploy, nhóm `www-data`.
- Không bao giờ chạy `db:seed` ở production (đã chặn trong code; dữ liệu mẫu có admin mật khẩu `123456`).
- Đổi `APP_KEY` sẽ đăng xuất mọi người và làm hỏng link xác thực email đang chờ — chỉ đổi khi lộ khóa.
- `composer audit` chạy trong CI ở mỗi lần push; có cảnh báo lỗ hổng → cập nhật gói rồi deploy.
- Khóa tài khoản nhân viên nghỉ việc ngay trên trang Khách hàng: phiên đang đăng nhập bị đăng xuất ở request kế tiếp.

## Mã từng chậu, in tem, máy quét (từ 26/11/2026)

- **Lần triển khai đầu:** `migrate` tự phát mã cho mọi chậu đang trên kệ (tồn + đã đặt chưa giao). Sau đó in tem cho từng cây ở Kho → chọn cây → **In tem chậu trong kho**, rồi dán tem lên chậu. Chưa dán xong thì chưa giao được những chậu đó, vì giao hàng bắt quét mã.
- **Nhập hàng:** nhập kho xong, mở phiếu nhập → **In tem** (tem của đúng phiếu đó) rồi dán trước khi đưa lên kệ.
- **Máy in tem nhiệt:** cài khổ giấy 50 × 30 mm trong driver. Trong hộp thoại in: khổ 50 × 30, lề None, tỉ lệ 100%, bỏ đầu trang / chân trang. Mỗi tem in ra một trang.
- **Máy quét USB / Bluetooth:** phải đọc được **QR (2D)**, chế độ bàn phím (HID), có gửi Enter sau mã. Bấm vào ô quét rồi quét.
- **Camera điện thoại:** cần site chạy **HTTPS** (trình duyệt chỉ cho mở camera trên HTTPS) và cho phép trình duyệt dùng camera. Header `Permissions-Policy` đã mở `camera=(self)`.
- **Mất tem / tem mờ:** Tra cứu chậu → gõ mã (in dưới QR) → **In lại tem**. Hàng hoàn của đơn giao trước khi có mã được phát mã mới; vào Tra cứu chậu để in tem.
- **Đối soát lệch "Số chậu trong kho lệch tồn":** không sửa tay. Kiểm tra xem có ai sửa DB trực tiếp không, rồi làm phiếu kiểm kê (quét) để đưa kho về đúng.


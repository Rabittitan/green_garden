# H8 — Báo cáo nghiệm thu GreenGarden Shop

**Ngày:** 17/11/2026, cập nhật số liệu 23/11/2026 · **Nhánh:** `claude/sweet-archimedes-sb7rwp` · **Môi trường chuẩn:** PHP 8.4 + MySQL 8.0 (CI GitHub Actions) · Laravel 12.71

## Kết luận

**Đạt cả 9 tiêu chí C1–C9.** Trong lúc nghiệm thu tìm thêm **5 lỗi**: 4 lỗi trong code và 1 lỗi trong script kiểm thử. Cả 5 đã sửa, mỗi lỗi có test **đỏ trên code cũ → xanh sau khi sửa**. Các phần **chưa kiểm được** trong môi trường này được liệt kê riêng ở cuối, chưa có phần nào được tính là "đạt".

| Chỉ số | Kết quả |
|---|---|
| Test tự động (MySQL thật) | **526 test qua**, 46.882 assertion, ~3 phút (lúc nghiệm thu 17/11: 370 test) |
| Larastan level 6 | 0 lỗi |
| Pint (định dạng) | sạch |
| `composer audit` | 0 lỗ hổng |
| Kịch bản trình duyệt 3 vai trò | **15/15 bước đạt** (lúc nghiệm thu: 12 bước; thêm sổ địa chỉ, đăng tin có ảnh, admin trên điện thoại); không có lỗi JS, lỗi 5xx hay vi phạm CSP |
| `shop:reconcile` sau kịch bản trình duyệt | **8/8 ✓** (3 đơn, 5 giao dịch, 21 bút toán kho) |

---

## Đối chiếu từng tiêu chí

| Mã | Tiêu chí | Bằng chứng | Kết quả |
|---|---|---|---|
| **C1** | Đúng nghiệp vụ; mỗi lỗi có test đỏ → xanh | 526 test xanh trên MySQL. Mọi lỗi tìm ra ở H1–H8 đều có test hồi quy (`tests/Feature/Regression/*`, cùng các test mang tên lỗi trong từng nhóm). Ở H8, độ đỏ-trước được kiểm lại bằng cách chạy test trên code cũ | ✅ |
| **C2** | Toàn vẹn dữ liệu | `shop:reconcile` (10 bất biến: kho = sổ kho, tiền thu/hoàn = payments, giảm giá dòng = đơn, lượt mã, điểm đánh giá, lượt hữu ích, đơn quá hạn, chi tiêu hạng, suất flash sale đã bán = dòng hàng chưa hủy, chậu trên kệ = tồn + đã đặt chưa giao) chạy ở 3 chỗ: trong ma trận trạng thái (sau mỗi ô), trên CI (DB mới + seed), và sau kịch bản trình duyệt. Schema đổi 2 lần sau khi chốt, đều theo yêu cầu của chủ shop và chỉ THÊM: K8 (22/11/2026) thêm 6 cột thông tin chi tiết vào `plants` (cho phép trống, CHECK `chk_plants_growth_rate`); 23/11/2026 thêm bảng `home_slides` (slide trang chủ, không khóa ngoại); 25/11/2026 thêm cột `users.permissions` (phân quyền nhân viên, NULL = đủ quyền như cũ) và ví mã (bảng `coupon_gift_rules`, cột `coupons.owner_user_id / gift_rule_id / source_order_id`), flash sale (bảng `flash_sales`, `flash_sale_items`, cột `order_items.flash_sale_item_id`), phiếu kiểm kê (bảng `stock_counts`, `stock_count_items`, cột `stock_movements.stock_count_item_id`), phí ship theo vùng (bảng `shipping_zones`, `shipping_zone_provinces`, cột `plants.weight_grams`); 26/11/2026 thêm kênh liên hệ (bảng `contact_channels`), mã từng chậu (bảng `plant_units`, `plant_unit_events`; migration phát mã cho hàng đang tồn), phiếu báo lỗi (bảng `defect_reports`, `defect_report_items`, cột `stock_movements.defect_report_item_id`), kiểm kê quét mã (bảng `stock_count_scans`, cột `stock_counts.unit_event_mark`). Không sửa cột cũ | ✅ |
| **C3** | Đồng thời | 12 kịch bản đua bằng **tiến trình PHP thật**, gồm: tranh cây cuối, bấm đúp nhiều tab, IPN đua với hết hạn, hủy đua với đặt mới, mã chứng từ, nhập kho đua với đặt hàng, đánh giá trùng, nhân viên hủy đua với IPN, giao đua với hủy, 2 admin cùng xác nhận hoàn tiền, 3 admin cùng xác nhận đơn, **admin sửa phạm vi mã giảm giá đúng lúc khách đặt hàng bằng mã đó (nhóm B, 11/2026)**. Kết quả: không tồn âm, không thu tiền 2 lần, không deadlock | ✅ |
| **C4** | Luồng khép kín | `OrderStateMatrixTest`: 12 hành động × 9 trạng thái = 108 ô. Ô được phép phải chạy xong và đối soát sạch; ô bị cấm phải không để lại tác dụng phụ. Mọi trạng thái đều tới được, và mọi trạng thái chưa kết thúc đều có lối ra. Kịch bản trình duyệt đi qua đủ các nút bấm thật của cả 3 vai trò | ✅ |
| **C5** | Phân quyền & bảo mật | Phân quyền: `RoutePermissionMatrixTest` tự đọc **71 route admin** và thử từng route với 4 vai trò; 404 và 403 không làm lộ id có tồn tại hay không. **Mới ở H8:** `AcceptanceCrawlTest` mở **mọi route GET × 4 vai trò** và chứng minh không route GET nào ghi DB. Test này đã được kiểm ngược: cài thử một lệnh ghi DB vào trang chủ thì test đỏ. IPN sai chữ ký không thay đổi gì. Khi `APP_DEBUG=false`, trang lỗi không lộ chi tiết. Có header bảo mật, CSP, giới hạn tần suất đăng nhập, chặn open redirect | ✅ |
| **C6** | Hiệu năng | `QueryBudgetTest`: 20 trang chính, mỗi trang ≤ 15 câu SQL, và số câu **không tăng** khi dữ liệu tăng. Ngoài production bật `preventLazyLoading` (cả bộ test không vướng lần nào). Mọi trang danh sách có thể phình đều đã phân trang; các danh sách không phân trang đều có giới hạn: 4 hạng, giỏ hàng, danh mục, và **sổ địa chỉ (mới đặt trần 10 ở H8)** | ✅ |
| **C7** | Vận hành | `docs/H7-van-hanh.md` + `deploy/`: cron `schedule:run`, queue worker (supervisor), sao lưu/khôi phục kèm diễn tập, deploy/rollback, checklist `.env`, IPN VNPay. Mọi callback thanh toán được ghi vào `payment_webhook_logs` (chữ ký, mã kết quả, IP thật) | ✅ (*) |
| **C8** | Chất lượng code | Pint sạch · Larastan level 6: 0 lỗi · CI GitHub (Test + Larastan) xanh, chặn merge khi đỏ · `composer audit` trong CI (đã bắt được 7 lỗ hổng Symfony ở H7) | ✅ |
| **C9** | Trải nghiệm | Crawler: **không trang nào trả 500**, với mọi route GET × 4 vai trò. Trang lỗi 403/404/419/429/500/503 bằng tiếng Việt. Email ở **mọi mốc tiền**: đặt hàng, đã thanh toán, **giao thành công/đã thu COD (mới)**, hủy (kèm số tiền sẽ hoàn), **tiền đến sau khi đơn đã hủy (mới)**, đã hoàn tiền; cùng với hướng dẫn trả hàng và lên hạng. Email chỉ gửi sau khi commit, gửi lỗi không làm hỏng giao dịch | ✅ |

(*) Phần tài liệu và script đã xong và đã kiểm cú pháp. Chưa chạy được trên máy chủ thật (xem mục cuối).

---

## Lỗi tìm ra trong H8 (đã sửa)

| # | Tiêu chí | Lỗi | Sửa | Test |
|---|---|---|---|---|
| 1 | C5 | `GET /don-hang/{mã}/thanh-toan` **ghi DB**: tạo giao dịch thanh toán, có thể chuyển đơn sang hết hạn. Trình duyệt prefetch link, hoặc site khác nhúng link, đều có thể kích hoạt | Đổi thành **POST + CSRF**. Nút trên trang đơn đổi thành form. Đặt đơn VNPay xong thì chuyển **thẳng** sang cổng thanh toán | `test_opening_a_payment_requires_post_with_csrf` (đỏ trên code cũ: 302 thay vì 405) |
| 2 | C6 | Sổ địa chỉ **không giới hạn**, và hiện ra cả ở trang đặt hàng | Trần 10 địa chỉ/khách; sổ đầy thì đặt hàng vẫn được nhưng không lưu thêm | `H8FindingsTest` (đỏ trên code cũ) |
| 3 | C3 | Thêm địa chỉ **không khóa theo user**: 2 lần thêm đồng thời có thể cùng thành "mặc định", đụng UNIQUE và gây lỗi 500. Checkout lưu địa chỉ cũng gặp rủi ro tương tự, dù đơn đã đặt thành công | Khóa dòng user khi thêm; lưu địa chỉ ở checkout chuyển thành việc phụ, đụng UNIQUE thì bỏ qua | `H8FindingsTest` |
| 4 | C9 | **Tiền đến sau khi đơn đã hủy** (IPN trễ, chuyển khoản muộn): hệ thống thu tiền và tạo yêu cầu hoàn đúng, nhưng **khách không được báo gì** | Email `LatePaymentRefund`: đã nhận X, đơn đã hủy, sẽ hoàn toàn bộ | `test_late_payment_on_cancelled_order_tells_customer_about_refund` (đỏ khi gỡ dòng gửi) |
| 5 | C9 | **Giao COD thành công** (shipper thu tiền mặt) không có email; đây cũng là lúc bắt đầu hạn đổi trả 7 ngày | Email `OrderCompleted`: số tiền mặt đã trả và hạn yêu cầu trả hàng | `test_completed_cod_order_emails_cash_receipt_and_return_deadline` |
| 6 | Kiểm thử | Script trình duyệt `roles.e2e.js` **không chạy được** theo đúng lệnh ghi trong file, vì `package.json` khai báo `"type": "module"`. Ở H5 script được chạy từ một bản sao nằm ngoài repo nên lỗi không lộ ra | Đổi tên thành `roles.e2e.cjs`; thêm chế độ `E2E_STUB_CDN=1` để vẫn kiểm được CSP trên máy không có internet | Chạy 12/12 bước đạt |

## Ngoại lệ đã xem xét và chấp nhận

- **GET có ghi DB, đã ghi rõ trong `AcceptanceCrawlTest`:**
  - Link xác thực email (ký số, chuẩn Laravel).
  - IPN VNPay: VNPay chỉ gọi bằng GET; có chữ ký và xử lý idempotent.
  - Mở trang đơn **đã quá hạn thanh toán** thì đơn tự hủy. Đây đúng là việc cron vẫn làm mỗi phút, không ai lợi dụng được để làm gì khác.
- **Quy tắc giữ nguyên:** đơn VNPay bỏ dở vẫn giữ lượt dùng mã "đơn đầu tiên" cho tới khi đơn hết hạn (B9). Mục đích là chống lạm dụng; thông báo lỗi có ghi rõ.

## Thay đổi sau nghiệm thu (18–26/11/2026)

Mỗi đợt đều chạy lại đủ: toàn bộ test trên MySQL, Larastan, Pint, kịch bản trình duyệt, `shop:reconcile`; lỗi sửa đều có test đỏ trên code cũ.

| Đợt | Nội dung | Ghi chú kiểm chứng |
|---|---|---|
| Rà soát A, C, U1 | Dùng `Format::money`, enum, `isAdmin()`, scope Payment; test đối chiếu 3 công thức chi tiêu; khoảng giá bộ lọc `100k ≤ p < 300k`… | Không đổi hành vi; `PriceRangeFilterTest` 11 mốc biên |
| Nhóm B | Admin sửa mã giảm giá: khóa `plants → coupons`, xét "đã dùng" sau khi khóa, thử lại khi deadlock | Test đua: logic cũ đỏ 3/3 (đổi được luật của mã đã có người dùng), mới xanh 5/5 |
| M1, L6 | Xóa code chết `Coupon::scopeUsableNow()` (lệch biên với quy tắc thật) và `PlantReview::getIsVerifiedPurchaseAttribute()` (luôn `true`) | Không còn chỗ nào tham chiếu; M2 giữ nguyên theo quyết định chủ shop |
| K1–K3 | Tiếng Việt toàn hệ thống, quên mật khẩu + mail bảo mật, giao diện nền tảng, đổi tên GreenGarden | Chống dò email, link dùng 1 lần, đăng xuất phiên cũ |
| K7 | Giao diện cửa hàng, mua hàng, tài khoản, quản trị | Khung admin không phụ thuộc CDN |
| K8 | Thông tin chi tiết cây (**đổi schema có duyệt**: 6 cột cho phép trống) | Có `down()`, đã thử rollback |
| K9 | Địa chỉ chọn Tỉnh → Phường/xã theo địa giới 01/07/2025 (34 tỉnh, 3.321 xã) | Không đổi schema; dữ liệu đối chiếu 2 nguồn độc lập |
| K10 | Dashboard doanh thu có biểu đồ theo ngày (giờ VN) | Tổng theo ngày = thẻ doanh thu |
| K11 | Đánh giá đăng ngay (chủ shop duyệt), trang đơn hoàn thành mời đánh giá | Bài bị gỡ mà sửa lại thì chờ duyệt |
| Admin điện thoại | 13 trang admin không tràn ngang ở 390px, có/không có CDN | Bước mới trong kịch bản trình duyệt |
| Tin tức | Admin đăng bài, ảnh tải lên, Markdown an toàn, nháp / hẹn giờ | Chặn SVG, HTML, `javascript:`; cần `storage:link` khi triển khai |
| Hóa đơn + ghi chú | In hóa đơn bán lẻ (khách + admin, A4, không phụ thuộc CDN); khách sửa ghi chú cho shop tới khi shop xác nhận đơn | Trang hóa đơn chỉ đọc; PDF xuất thử 1 trang A4 |
| Trang chủ | Slide ảnh admin quản lý (**bảng mới `home_slides`**, tối đa 10, slider tự viết không cần CDN); mục "Sản phẩm hot" = bán chạy 30 ngày | Hot chỉ tính đơn chờ xác nhận / đã xác nhận / đang giao / hoàn thành, cây đang bán |
| Góp ý 24/11 (đợt 1–6) | Thống kê theo kỳ; phân quyền nhân viên; ví mã + tặng mã; flash sale; phiếu kiểm kê; phí ship theo vùng + cân nặng (**5 migration mới, đều chỉ thêm**, xem C2) | Mỗi luồng ghi tiền / kho có test đua; `shop:reconcile` thêm bất biến suất flash sale |
| Góp ý 26/11 | Thống kê: 3 biểu đồ tròn (tình trạng đơn, doanh thu theo danh mục, theo cách thanh toán), màu cố định theo đối tượng, bảng màu đã kiểm tra mù màu, có vòng CSS dự phòng khi CDN lỗi. Trình soạn bài có "Xem trước" + ảnh thu nhỏ. "Sản phẩm hot" thành dải trượt ngang tự chạy; slide luôn tự chạy (cả khi máy bật giảm chuyển động / chuột nằm trên slide), có nút tạm dừng | **Lỗi ảnh bài viết không hiện**: thiếu link `public/storage` thì `/storage/...` rơi vào route ký của disk riêng tư → 403. Sửa `config/filesystems.php`: disk `public` tự phát file khi thiếu link, disk `local` thôi chiếm `/storage`. Test hồi quy đỏ trên code cũ |
| Góp ý 26/11 (đợt 2) | Bong bóng mạng xã hội (Zalo / Facebook / TikTok, admin nhập link). **Mã riêng từng chậu**: phát mã khi nhập / tồn đầu kỳ, in tem QR 50×30 mm cho máy in nhiệt, tra cứu lịch sử chậu; **quét mã khi đóng gói** mới giao được; nhận hoàn quét chậu hỏng. **Phiếu báo lỗi** (nhân viên lập, quản trị viên khác duyệt, kèm ảnh) thay "ghi hư hỏng"; **báo cáo hàng lỗi** theo loại lỗi / cây / nhà cung cấp. **Kiểm kê quét mã** thay gõ số đếm | Bất biến #10 trong `shop:reconcile` và trong mọi test; 3 kịch bản đua mới (2 đơn quét cùng chậu, duyệt phiếu lỗi đua với giao, chốt kiểm kê đua với đặt hàng); camera thử bằng video giả (jsQR đọc đúng mã); PDF tem: mỗi tem 1 trang 50×30 mm. Quét bằng camera cần HTTPS |

## Chưa kiểm được trong môi trường này (không tính là đạt)

| Hạng mục | Lý do | Cách kiểm khi triển khai |
|---|---|---|
| VNPay sandbox thật | Không có mã merchant/khóa; sandbox không truy cập được từ máy này | `docs/H7-van-hanh.md` mục 4: 3 kịch bản (thành công, khách hủy, quá 15 phút), sau mỗi kịch bản xem `payment_webhook_logs` và chạy `shop:reconcile` |
| Script `deploy/`, nginx, supervisor, cron | Không có máy chủ thật | Chạy `deploy.sh` trên staging (kể cả bước `storage:link` và khối nginx `/storage/` mới); diễn tập `restore-db.sh` |
| Tải CSS/JS/font/ảnh từ CDN thật (Bootstrap, Font Awesome, Chart.js) | Máy này bị chặn internet; kịch bản trình duyệt dùng CDN giả (CSP vẫn được kiểm, và đã kiểm ngược: CSP sai thì bắt được) | Chạy `roles.e2e.cjs` **không** có `E2E_STUB_CDN` trên máy có internet |
| Gửi email qua SMTP thật | Test dùng mail giả | Đặt hàng thử trên staging với SMTP thật |
| Kịch bản trình duyệt trong CI | Hiện chạy tay (cần server + dữ liệu mẫu) | Có thể thêm job CI riêng nếu cần |
| PHP 8.4 ở máy dev | Máy này chỉ có PHP 8.3; kết quả chuẩn là của CI chạy PHP 8.4 | Đã có trên CI |

## Để giai đoạn sau (đã thống nhất từ kế hoạch v2)

Trả một phần đơn hàng · API hoàn tiền tự động của VNPay · cổng MoMo · hóa đơn VAT · nhiều kho.

Hạn chế đã biết (ưu tiên thấp): ảnh chèn **trong nội dung** bài tin tức không tự xóa khi sửa/xóa bài (ảnh bìa thì có), file thừa chỉ tốn dung lượng.

## Tóm tắt các giai đoạn

| Giai đoạn | Nội dung |
|---|---|
| G1–G7 | Thiết kế lại DB (MySQL, CHECK constraint, sổ kho, máy trạng thái), bán hàng, thanh toán, voucher, hạng thành viên, kho |
| H0 | CI GitHub (MySQL 8), Pint, Larastan, ngân sách câu SQL |
| H1 | Sửa B1–B3, B6–B8 (hạ hạng thủ công bị tự nâng lại, mail thiếu tên, hạ ngưỡng hạng, doanh thu thuần, trùng mã phiếu, log phình) |
| H2 | Email ở các mốc đơn/tiền, hướng dẫn trả hàng |
| H3 | Nhập hàng: nhà cung cấp → đề xuất (4 mắt) → phiếu nhập |
| H4 | Đánh giá & hỏi đáp |
| H5 | Rà luồng toàn diện: ma trận trạng thái, ma trận quyền, gây lỗi giữa chừng, đua song song, trình duyệt; Larastan 498 → 0 lỗi |
| H6 | Bảo mật & hiệu năng: CSP/header, giới hạn đăng nhập, trang lỗi tiếng Việt, chặn N+1 |
| H7 | Vận hành: múi giờ DB, proxy tin cậy, chặn seed ở production, sổ tay vận hành, script deploy/sao lưu, vá lỗ hổng Symfony, sửa yêu cầu PHP ≥ 8.4.1 |
| H8 | Nghiệm thu C1–C9 (báo cáo này) |

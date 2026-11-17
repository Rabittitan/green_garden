/*
 * Kịch bản trình duyệt 3 vai trò (khách / nhân viên / quản trị) — H5, bổ sung H8 (CSP, thanh toán lại, trang lỗi).
 * Chuẩn bị: php artisan migrate:fresh --seed ; tạo nhân viên staff@greenlife.test / 123456 ;
 *           PAYMENT_GATEWAY=fake, APP_ENV=local ; php artisan serve
 * (Máy không có internet: thêm E2E_STUB_CDN=1 để giả lập CDN, vẫn kiểm được CSP)
 * Chạy:     npm i -D playwright && npx playwright install chromium && APP_URL=http://127.0.0.1:8000 node tests/Browser/roles.e2e.cjs
 * Cuối cùng: php artisan shop:reconcile phải toàn ✓
 */
const { chromium } = require('playwright');
const B = process.env.APP_URL || 'http://127.0.0.1:8000';
(async () => {
  const browser = await chromium.launch();
  const errors = [];
  let failed = 0;
  const ctx = async () => { const c = await browser.newContext();
    // E2E_STUB_CDN=1: máy không ra được internet -> trả nội dung giả cho CDN/ảnh. CSP vẫn được trình duyệt áp
    // TRƯỚC khi gửi request, nên tài nguyên bị CSP chặn nhầm vẫn hiện lỗi "Refused to load" ở console.
    if (process.env.E2E_STUB_CDN) {
      await c.route(/^https:\/\/(cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com|images\.unsplash\.com|placehold\.co|fonts\.(googleapis|gstatic)\.com)\//, r => {
        const u = r.request().url();
        const type = u.endsWith('.css') ? 'text/css' : u.endsWith('.js') ? 'application/javascript' : 'image/svg+xml';
        r.fulfill({ status: 200, contentType: type, body: type === 'image/svg+xml' ? '<svg xmlns="http://www.w3.org/2000/svg"/>' : '' });
      });
    }
    const p = await c.newPage();
    p.on('pageerror', e => errors.push('JS: ' + e.message));
    // H8: CSP (H6) chặn nhầm CSS/JS/font/ảnh -> trình duyệt báo ở console; test PHP không thấy được lỗi này
    p.on('console', m => { if (m.type() === 'error' && /Content Security Policy|Refused to/i.test(m.text())) errors.push('CSP: ' + m.text()); });
    p.on('requestfailed', r => { if (!/favicon/.test(r.url())) errors.push('Tải lỗi: ' + r.url() + ' ' + (r.failure() || {}).errorText); });
    p.on('response', r => { if (r.status() >= 500) errors.push('HTTP ' + r.status() + ' ' + r.url()); });
    p.on('dialog', d => d.accept()); return p; };
  const step = async (name, fn) => { try { await fn(); console.log('OK  ', name); } catch (e) { failed++; console.log('FAIL', name, '-', e.message.split('\n')[0]); } };
  const login = async (p, email) => { await p.goto(B + '/dang-nhap'); await p.fill('input[name=email]', email); await p.fill('input[name=password]', '123456');
    await Promise.all([p.waitForNavigation(), p.click('form[action$="dang-nhap"] button')]); };
  const click = async (p, text) => Promise.all([p.waitForNavigation(), p.click(text)]);
  const expect = async (p, text) => { if (!(await p.content()).includes(text)) throw new Error('không thấy: ' + text); };

  const customer = await ctx(), staff = await ctx(), admin = await ctx();
  await login(customer, 'khach@greenlife.test'); await login(staff, 'staff@greenlife.test'); await login(admin, 'admin@greenlife.test');
  let orderUrl;

  // ===== KHÁCH: đặt COD
  await step('khách: thêm cây, đặt COD', async () => {
    await customer.goto(B + '/cay-canh/1'); await customer.fill('input[name=quantity]', '1');
    await click(customer, 'form[action*="gio-hang/them"] button');
    await customer.goto(B + '/dat-hang'); await customer.check('#pm-cod');
    await click(customer, '#submit-btn'); orderUrl = customer.url(); await expect(customer, 'Chờ xác nhận');
  });
  // ===== NHÂN VIÊN: xử lý đơn
  await step('nhân viên: xác nhận -> giao -> hoàn thành', async () => {
    await staff.goto(B + '/admin/orders'); await click(staff, 'text=Chi tiết');
    await click(staff, 'text=Xác nhận đơn');
    await staff.fill('input[name=shipping_carrier]', 'GHN'); await staff.fill('input[name=tracking_code]', 'E2E-STAFF');
    await click(staff, 'text=Giao cho vận chuyển'); await click(staff, 'text=Giao thành công'); await expect(staff, 'Đơn đã hoàn thành');
  });
  await step('nhân viên: không thấy menu quản trị tiền / mã / hạng', async () => {
    await staff.goto(B + '/admin'); const html = await staff.content();
    if (html.includes('Mã giảm giá') || html.includes('Hạng thành viên')) throw new Error('nhân viên thấy menu chỉ dành cho quản trị');
    const r = await staff.goto(B + '/admin/coupons'); if (r.status() !== 403) throw new Error('vào được /admin/coupons: ' + r.status());
  });
  // ===== KHÁCH: đánh giá + hỏi đáp
  await step('khách: đánh giá cây đã nhận + đặt câu hỏi', async () => {
    await customer.goto(B + '/cay-canh/1'); await customer.selectOption('select[name=rating]', '5');
    await customer.fill('textarea[name=content] >> nth=0', 'Cây khỏe, đóng gói kỹ');
    await click(customer, 'form[action$="danh-gia"] button'); await expect(customer, 'Chờ duyệt');
    await customer.fill('form[action$="hoi-dap"] textarea', 'Bao lâu thay chậu một lần?');
    await click(customer, 'form[action$="hoi-dap"] textarea >> xpath=../button'); await expect(customer, 'Bao lâu thay chậu');
  });
  // ===== NHÂN VIÊN: duyệt đánh giá, trả lời hỏi đáp, lập yêu cầu nhập
  await step('nhân viên: duyệt đánh giá + trả lời hỏi đáp', async () => {
    await staff.goto(B + '/admin/reviews'); await click(staff, 'button:has-text("Duyệt")');
    await staff.goto(B + '/admin/comments'); await staff.fill('input[name=content]', 'Khoảng 1 năm bạn nhé.'); await click(staff, 'button:has-text("Trả lời")');
    await customer.goto(B + '/cay-canh/1'); await expect(customer, 'Shop trả lời'); await expect(customer, '5</strong>/5');
  });
  await step('nhân viên: lập yêu cầu nhập từ cây dưới ngưỡng, gửi duyệt, KHÔNG tự duyệt được', async () => {
    await staff.goto(B + '/admin/purchase-requests'); await click(staff, 'text=Lập từ cây dưới ngưỡng');
    await click(staff, 'button:has-text("Gửi duyệt")'); await expect(staff, 'Chờ quản trị viên khác người lập duyệt');
  });
  // ===== QUẢN TRỊ: duyệt yêu cầu nhập; nhân viên nhập kho
  await step('quản trị: duyệt yêu cầu nhập; nhân viên lập phiếu + nhập kho', async () => {
    await admin.goto(B + '/admin/purchase-requests'); await click(admin, 'text=Chi tiết');
    await click(admin, 'text=Duyệt (theo số'); await expect(admin, 'Đã duyệt');
    await staff.goto(B + '/admin/purchase-requests'); await click(staff, 'text=Chi tiết');
    await click(staff, 'text=Lập phiếu nhập'); await click(staff, 'text=Lưu phiếu nháp');
    await click(staff, 'button:has-text("Nhập kho")'); await expect(staff, 'Đã nhập kho');
  });
  // ===== KHÁCH: đơn VNPay rồi hủy -> quản trị hoàn tiền
  await step('khách: đơn VNPay, thanh toán, tự hủy', async () => {
    await customer.goto(B + '/cay-canh/3'); await click(customer, 'form[action*="gio-hang/them"] button');
    await customer.goto(B + '/dat-hang'); await customer.check('#pm-vnpay');
    await Promise.all([customer.waitForURL(/gia-lap/), customer.click('#submit-btn')]);
    await Promise.all([customer.waitForURL(/don-hang\/GL/), customer.click('text=Thanh toán thành công')]);
    await customer.fill('form[action$="huy"] input[name=reason]', 'Đặt nhầm'); await click(customer, 'text=Hủy đơn hàng');
    await expect(customer, 'Chờ hoàn tiền');
  });
  await step('khách: VNPay thất bại -> bấm nút Thanh toán (POST) trên trang đơn -> thành công', async () => {
    await customer.goto(B + '/cay-canh/2'); await click(customer, 'form[action*="gio-hang/them"] button');
    await customer.goto(B + '/dat-hang'); await customer.check('#pm-vnpay');
    await Promise.all([customer.waitForURL(/gia-lap/), customer.click('#submit-btn')]);
    await Promise.all([customer.waitForURL(/don-hang\/GL/), customer.click('text=Hủy / thất bại')]);
    await expect(customer, 'Chờ thanh toán');
    await Promise.all([customer.waitForURL(/gia-lap/), customer.click('form[action$="thanh-toan"] button')]);
    await Promise.all([customer.waitForURL(/don-hang\/GL/), customer.click('text=Thanh toán thành công')]);
    await expect(customer, 'Thanh toán thành công');
  });
  await step('quản trị: xác nhận đã hoàn tiền', async () => {
    await admin.goto(B + '/admin/orders?payment_status=refund_pending'); await click(admin, 'text=Chi tiết');
    await admin.fill('input[name=provider_ref]', 'VNP-RF-E2E'); await click(admin, 'button:has-text("Đã hoàn")');
    await expect(admin, 'Đã hoàn tiền');
  });
  await step('quản trị: tạo mã giảm giá, khách dùng được', async () => {
    await admin.goto(B + '/admin/coupons/create'); await admin.fill('input[name=code]', 'E2E15'); await admin.fill('input[name=name]', 'E2E');
    await admin.fill('input[name=value]', '15'); await click(admin, 'button:has-text("Lưu")'); await expect(admin, 'E2E15');
    await customer.goto(B + '/cay-canh/5'); await click(customer, 'form[action*="gio-hang/them"] button');
    await customer.goto(B + '/dat-hang'); await customer.fill('input[name=coupon_code]', 'e2e15');
    await click(customer, 'form[action$="ap-ma"] button'); await expect(customer, 'Mã E2E15');
  });
  await step('trang lỗi tiếng Việt (404, 403) có CSS, không lộ chi tiết', async () => {
    const r = await customer.goto(B + '/khong-co-trang-nay'); if (r.status() !== 404) throw new Error('404? ' + r.status());
    await expect(customer, 'Không tìm thấy trang');
    const f = await customer.goto(B + '/admin'); if (f.status() !== 403) throw new Error('403? ' + f.status());
    await expect(customer, 'Không có quyền truy cập');
    const h = await customer.goto(B + '/'); const csp = h.headers()['content-security-policy'] || '';
    if (!csp.includes("frame-ancestors 'none'")) throw new Error('thiếu CSP');
  });
  console.log(errors.length ? 'LỖI JS/5xx:\n' + errors.join('\n') : 'Không có lỗi JS / 5xx.');
  console.log(failed ? `${failed} bước FAIL` : 'TẤT CẢ BƯỚC ĐẠT');
  await browser.close();
})();

/*
 * Thông báo nổi (partials/toasts) + giữ chỗ cuộn.
 *  - Toast: tự ẩn sau data-ttl ms, dừng đếm khi rê chuột / đang focus; nút × đóng ngay.
 *    window.ggToast(type, text) để script khác hiện thông báo không cần tải lại trang.
 *  - Giữ chỗ cuộn: gửi form POST rồi được đưa về CHÍNH trang đó (VD "Thêm vào giỏ", lưu trong trang quản trị)
 *    thì cuộn lại đúng chỗ cũ thay vì nhảy lên đầu trang.
 */
(function () {
    var KEY = 'gg-scroll';
    var box = document.querySelector('[data-toasts]');

    function arm(t) {
        var ttl = parseInt(t.dataset.ttl || '5000', 10), left = ttl, started = Date.now(), timer = null;
        function close() { t.classList.add('hide'); setTimeout(function () { t.remove(); }, 250); }
        function run() { started = Date.now(); timer = setTimeout(close, left); }
        function pause() { if (timer) { clearTimeout(timer); timer = null; left -= Date.now() - started; } }
        t.querySelector('[data-toast-close]').addEventListener('click', close);
        t.addEventListener('mouseenter', pause); t.addEventListener('mouseleave', run);
        t.addEventListener('focusin', pause); t.addEventListener('focusout', run);
        run();
    }

    if (box) {
        box.querySelectorAll('[data-toast]').forEach(arm);
        window.ggToast = function (type, text) {
            var t = document.createElement('div'), icon = { success: 'fa-circle-check', warning: 'fa-circle-info', error: 'fa-triangle-exclamation' }[type] || 'fa-circle-info';
            t.className = 'gg-toast ' + type; t.setAttribute('role', type === 'success' ? 'status' : 'alert');
            t.dataset.toast = ''; t.dataset.ttl = type === 'success' ? '4000' : '8000';
            t.innerHTML = '<i class="fa ' + icon + '" aria-hidden="true"></i><div class="gg-toast-text"></div>'
                + '<button type="button" class="gg-toast-close" data-toast-close aria-label="Đóng thông báo">&times;</button>';
            t.querySelector('.gg-toast-text').textContent = text;
            box.appendChild(t); arm(t);
        };
    }

    var here = location.pathname + location.search;
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || (f.method || '').toLowerCase() !== 'post' || f.target === '_blank') return;
        try { sessionStorage.setItem(KEY, JSON.stringify({ path: here, y: window.scrollY, t: Date.now() })); } catch (err) { /* chế độ riêng tư */ }
    }, true);
    try {
        var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        sessionStorage.removeItem(KEY);
        if (saved && saved.path === here && !location.hash && Date.now() - saved.t < 30000 && saved.y > 0) {
            if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
            window.scrollTo(0, saved.y);
        }
    } catch (err) { /* không đọc được sessionStorage: bỏ qua */ }
})();

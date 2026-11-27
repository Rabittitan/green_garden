/*
 * Quét chậu theo dòng đơn (trang chi tiết đơn, admin). Dựa trên ô quét chung (scanner.js, sự kiện "gg:scan").
 *
 *  data-pack-mode="ship"    : Đóng gói & giao. Nhận chậu "trong kho" đúng cây của một dòng còn thiếu; đủ hết mới cho bấm giao.
 *                             Gửi unit_codes[] (server kiểm lại toàn bộ trong OrderService::ship).
 *  data-pack-mode="damaged" : Nhận hàng hoàn. Quét tem chậu HỎNG của đơn; số tốt / hỏng mỗi dòng tự tính theo số chậu quét.
 *                             Gửi damaged_codes[] + scanned=1 (server kiểm đúng chậu của đơn trong OrderService::receiveReturn).
 */
(function () {
    document.querySelectorAll('[data-pack]').forEach(function (form) {
        var ship = form.dataset.packMode === 'ship';
        var lines = JSON.parse(form.querySelector('[data-pack-lines]').textContent);
        var scanner = form.querySelector('[data-scanner]'), inputs = form.querySelector('[data-pack-inputs]');
        var submit = form.querySelector('[data-pack-submit]'), flag = form.querySelector('[data-pack-scanned]');
        var picked = {};
        lines.forEach(function (l) { l.got = []; });
        inputs.innerHTML = '';                                               // bắt đầu lại sau lỗi: quét lại cho chắc
        if (flag) flag.value = '1';                                          // có JS: gửi đúng danh sách chậu hỏng đã quét

        function render() {
            inputs.innerHTML = '';
            lines.forEach(function (l) {
                var row = form.querySelector('[data-pack-row="' + l.item + '"]'), chips = row.querySelector('[data-pack-chips]');
                chips.innerHTML = '';
                l.got.forEach(function (u) {
                    var li = document.createElement('li'), btn = document.createElement('button');
                    li.textContent = u.display; btn.type = 'button'; btn.textContent = '×'; btn.setAttribute('aria-label', 'Bỏ chậu ' + u.display);
                    btn.addEventListener('click', function () { l.got = l.got.filter(function (x) { return x.code !== u.code; }); delete picked[u.code]; render(); });
                    li.appendChild(btn); chips.appendChild(li);
                    var h = document.createElement('input'); h.type = 'hidden'; h.name = ship ? 'unit_codes[]' : 'damaged_codes[]'; h.value = u.code;
                    inputs.appendChild(h);
                });
                if (ship) {
                    row.querySelector('[data-pack-count]').textContent = l.got.length;
                    row.classList.toggle('table-success', l.got.length === l.qty);
                } else {
                    row.querySelector('[data-pack-damaged]').value = l.got.length;
                    row.querySelector('[data-pack-good]').value = l.qty - l.got.length;
                }
            });
            if (submit) submit.disabled = ship && lines.some(function (l) { return l.got.length < l.qty; });
        }

        if (!ship) form.querySelectorAll('[data-pack-damaged],[data-pack-good]').forEach(function (i) { i.readOnly = true; });

        scanner.addEventListener('gg:scan', function (e) {
            if (!e.detail.ok) return;
            var u = e.detail.unit, line;
            if (picked[u.code]) return scanner.ggAccept(false, 'Chậu ' + u.display + ' đã quét rồi.');
            if (ship) {
                if (u.status !== 'in_stock') return scanner.ggAccept(false, 'Chậu ' + u.display + ' đang "' + u.status_label + '", không giao được.');
                line = lines.find(function (l) { return l.plant === u.plant_id && l.got.length < l.qty; });
                if (!line) {
                    var full = lines.find(function (l) { return l.plant === u.plant_id; });
                    return scanner.ggAccept(false, full ? 'Đã quét đủ ' + full.qty + ' chậu "' + full.name + '".' : 'Cây "' + u.plant_name + '" không có trong đơn này.');
                }
            } else {
                line = lines.find(function (l) { return l.codes.indexOf(u.code) >= 0; });
                if (!line) return scanner.ggAccept(false, 'Chậu ' + u.display + ' không phải chậu đã giao cho đơn này.');
            }
            line.got.push(u); picked[u.code] = true;
            scanner.ggAccept(true, (ship ? 'Đã nhận ' : 'Ghi hỏng ') + u.display + ' · ' + u.plant_name);
            render();
        });

        form.addEventListener('submit', function (e) {
            if (ship && lines.some(function (l) { return l.got.length < l.qty; })) {
                e.preventDefault(); scanner.ggAccept(false, 'Chưa quét đủ chậu cho mọi dòng.');
            }
        });
        render();
    });
})();

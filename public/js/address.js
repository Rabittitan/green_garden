/*
 * Ô chọn địa chỉ (K9): đổi Tỉnh/thành -> tải danh sách Phường/xã từ file tĩnh (cùng nguồn server dùng để kiểm tra).
 * Gắn vào mọi khối [data-gg-address]; khối có hàm ggSet({province, ward}) để nút "chọn từ sổ địa chỉ" điền sẵn.
 */
(function () {
    var cache = {};
    function load(url) {
        if (!cache[url]) {
            cache[url] = fetch(url, { credentials: 'same-origin' }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            });
            cache[url].catch(function () { delete cache[url]; });          // lỗi mạng: lần sau thử lại
        }
        return cache[url];
    }
    function setup(box) {
        var p = box.querySelector('[data-gg-province]'), w = box.querySelector('[data-gg-ward]');
        var note = box.querySelector('[data-gg-address-note]'), old = box.querySelector('[data-gg-old]');
        if (!p || !w) return;
        function fill(province, ward) {
            w.disabled = true;
            return load(box.dataset.ggAddress).then(function (d) {
                var found = d.provinces.filter(function (x) { return x.name === province; })[0];
                var list = found ? found.wards : [];
                w.innerHTML = '';
                w.add(new Option(province ? '-- Chọn phường / xã --' : '-- Chọn tỉnh / thành phố trước --', ''));
                list.forEach(function (n) { w.add(new Option(n, n)); });
                w.disabled = !list.length;
                var ok = !!ward && list.indexOf(ward) >= 0;
                if (ok) w.value = ward;
                return ok;
            }, function () {
                w.disabled = false;                                         // không tải được: giữ danh sách đang có
                return false;
            });
        }
        p.addEventListener('change', function () { if (note) note.hidden = true; fill(p.value, ''); });
        box.ggSet = function (a) {
            var hasP = Array.prototype.some.call(p.options, function (o) { return o.value && o.value === a.province; });
            p.value = hasP ? a.province : '';
            p.dispatchEvent(new CustomEvent('gg:province'));                 // báo trang khác (vd tính lại phí ship) mà không xóa xã
            fill(p.value, a.ward).then(function (ok) {
                if (!note) return;
                note.hidden = hasP && ok;
                if (old) old.textContent = [a.ward, a.province].filter(Boolean).join(', ');
            });
        };
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-gg-address]'), setup);
})();

/*
 * Thanh công cụ soạn bài (Markdown): chữ đậm, tiêu đề, danh sách, link, chèn ảnh, xem trước.
 * "Chèn ảnh": tải ảnh lên server (kèm CSRF của form), nhận lại đoạn ![mô tả](đường dẫn), chèn vào chỗ con trỏ và hiện ảnh thu nhỏ.
 * "Xem trước": gửi nội dung đang gõ cho server dựng HTML (cùng hàm với trang bài, đã lọc an toàn) và hiện thay khung soạn.
 * Ảnh bìa: chọn file là thấy ngay ảnh sẽ dùng.
 */
(function () {
    document.querySelectorAll('[data-editor-for]').forEach(function (bar) {
        var ta = document.getElementById(bar.dataset.editorFor), status = bar.querySelector('[data-editor-status]');
        var token = (ta.form.querySelector('input[name=_token]') || {}).value;
        function insert(before, after, placeholder) {
            var s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e) || placeholder || '';
            ta.setRangeText(before + sel + (after || ''), s, e, 'end');
            ta.focus();
        }
        function lineStart(prefix) {
            var s = ta.selectionStart, start = ta.value.lastIndexOf('\n', s - 1) + 1;
            ta.setRangeText(prefix, start, start, 'end');
            ta.focus();
        }
        bar.querySelectorAll('[data-md]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                ({ bold: function () { insert('**', '**', 'chữ đậm'); },
                   heading: function () { lineStart('## '); },
                   list: function () { lineStart('- '); },
                   link: function () { insert('[', '](https://)', 'chữ hiển thị'); } })[btn.dataset.md]();
            });
        });
        var file = bar.querySelector('[data-md-image]');
        file && file.addEventListener('change', function () {
            if (!file.files.length) return;
            var fd = new FormData(); fd.append('image', file.files[0]); fd.append('_token', token);
            status.textContent = 'Đang tải ảnh...';
            fetch(bar.dataset.uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error((res.j.errors && res.j.errors.image && res.j.errors.image[0]) || res.j.message || 'Lỗi tải ảnh');
                    insert('\n\n' + res.j.markdown + '\n\n', '', '');
                    status.textContent = '';
                    var img = document.createElement('img'); img.src = res.j.url; img.alt = ''; img.className = 'gg-editor-thumb';
                    img.onerror = function () { status.textContent = 'Đã lưu ảnh nhưng máy chủ không trả được file ảnh (' + res.j.url + '). Báo người quản trị kiểm tra thư mục storage.'; };
                    status.append(img, document.createTextNode(' Đã chèn ảnh. Sửa phần "Mô tả ảnh" cho đúng nội dung, bấm "Xem trước" để xem cả bài.'));
                })
                .catch(function (err) { status.textContent = err.message; })
                .finally(function () { file.value = ''; });
        });

        var btn = bar.querySelector('[data-md-preview]'), box = ta.parentNode.querySelector('[data-editor-preview]');
        btn && box && btn.addEventListener('click', function () {
            var label = btn.querySelector('span');
            if (!box.hidden) {                                                  // đang xem trước -> quay lại soạn
                box.hidden = true; ta.hidden = false; label.textContent = 'Xem trước'; btn.setAttribute('aria-pressed', 'false'); ta.focus();
                return;
            }
            var fd = new FormData(); fd.append('content', ta.value); fd.append('_token', token);
            label.textContent = 'Đang dựng...';
            fetch(bar.dataset.previewUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('Không xem trước được (mã ' + r.status + ').'); return r.json(); })
                .then(function (j) {
                    box.innerHTML = j.html.trim() !== '' ? j.html : '<p class="text-muted">Chưa có nội dung.</p>';   // HTML đã lọc ở server
                    box.hidden = false; ta.hidden = true; label.textContent = 'Soạn tiếp'; btn.setAttribute('aria-pressed', 'true');
                })
                .catch(function (err) { label.textContent = 'Xem trước'; status.textContent = err.message; });
        });
    });

    document.querySelectorAll('[data-cover-input]').forEach(function (input) {
        var preview = input.parentNode.querySelector('[data-cover-preview]');
        input.addEventListener('change', function () {
            if (!preview) return;
            if (!input.files.length) { preview.hidden = true; return; }
            var reader = new FileReader();                                      // data: URL (CSP cho phép img-src data:)
            reader.onload = function () { preview.src = reader.result; preview.hidden = false; };
            reader.readAsDataURL(input.files[0]);
        });
    });
})();

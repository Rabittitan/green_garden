/*
 * Thanh công cụ soạn bài (Markdown): chữ đậm, tiêu đề, danh sách, link, chèn ảnh.
 * "Chèn ảnh": tải ảnh lên server (kèm CSRF của form), nhận lại đoạn ![mô tả](đường dẫn) và chèn vào chỗ con trỏ.
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
                    status.textContent = 'Đã chèn ảnh. Sửa phần "Mô tả ảnh" cho đúng nội dung.';
                })
                .catch(function (err) { status.textContent = err.message; })
                .finally(function () { file.value = ''; });
        });
    });
})();

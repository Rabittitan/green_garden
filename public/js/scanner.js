/*
 * Ô quét mã chậu dùng chung (giao hàng, phiếu báo lỗi, kiểm kê, tra cứu).
 *
 *   <div data-scanner data-check-url="/admin/units/check"> ... partial admin/partials/scanner ... </div>
 *
 * - Máy quét USB / Bluetooth: hoạt động như bàn phím, gõ mã vào ô rồi nhấn Enter -> nhận mã.
 * - Camera điện thoại: bấm "Quét bằng camera". Dùng BarcodeDetector nếu trình duyệt có, nếu không thì tải jsQR (tự host).
 * Mỗi mã đọc được: hỏi server (GET check-url?code=) rồi phát sự kiện "gg:scan" trên phần tử [data-scanner]
 * với detail = { ok, unit: {code, display, plant_id, plant_name, status, status_label} | message }.
 * Trang nào dùng thì tự nghe "gg:scan" và quyết định nhận / từ chối chậu.
 */
(function () {
    function beep(ok) {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)(), o = ctx.createOscillator(), g = ctx.createGain();
            o.frequency.value = ok ? 1046 : 220; g.gain.value = 0.08; o.connect(g); g.connect(ctx.destination);
            o.start(); o.stop(ctx.currentTime + (ok ? 0.08 : 0.25));
        } catch (e) { /* không có âm thanh cũng không sao */ }
        if (navigator.vibrate) navigator.vibrate(ok ? 40 : [80, 60, 80]);
    }

    function loadJsQR(src) {
        return new Promise(function (resolve, reject) {
            if (window.jsQR) return resolve(window.jsQR);
            var s = document.createElement('script'); s.src = src;
            s.onload = function () { resolve(window.jsQR); }; s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    document.querySelectorAll('[data-scanner]').forEach(function (root) {
        var input = root.querySelector('[data-scan-input]'), status = root.querySelector('[data-scan-status]');
        var camBtn = root.querySelector('[data-scan-camera]'), box = root.querySelector('[data-scan-video-box]');
        var video = root.querySelector('video'), stream = null, last = '', lastAt = 0;

        function say(text, ok) { status.textContent = text; status.className = 'gg-scan-status ' + (ok ? 'ok' : 'bad'); }

        root.ggAccept = function (ok, text) { beep(ok); say(text, ok); };    // trang dùng gọi để báo nhận / từ chối

        function lookup(raw) {
            var code = String(raw || '').trim();
            if (!code) return;
            var now = Date.now();
            if (code === last && now - lastAt < 1500) return;                // camera đọc lặp cùng 1 tem
            last = code; lastAt = now;
            fetch(root.dataset.checkUrl + '?code=' + encodeURIComponent(code), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    var detail = res.ok ? { ok: true, unit: res.j } : { ok: false, message: res.j.message || 'Không kiểm tra được mã.' };
                    if (!res.ok) root.ggAccept(false, detail.message);
                    root.dispatchEvent(new CustomEvent('gg:scan', { detail: detail }));
                })
                .catch(function () { root.ggAccept(false, 'Mất kết nối, quét lại.'); });
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); lookup(input.value); input.value = ''; }
        });

        function stopCamera() {
            if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null; box.hidden = true; camBtn.querySelector('span').textContent = 'Quét bằng camera';
        }

        camBtn && camBtn.addEventListener('click', function () {
            if (stream) return stopCamera();
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return say('Trình duyệt không mở được camera (cần HTTPS). Dùng máy quét hoặc gõ mã.', false);
            }
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (s) {
                stream = s; video.srcObject = s; box.hidden = false; video.play();
                camBtn.querySelector('span').textContent = 'Tắt camera';
                var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
                var detector = ('BarcodeDetector' in window) ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null;
                var decode = detector
                    ? function () { return detector.detect(video).then(function (r) { return r.length ? r[0].rawValue : null; }); }
                    : function () {
                        return loadJsQR(root.dataset.jsqrSrc).then(function (jsQR) {
                            var w = video.videoWidth, h = video.videoHeight; if (!w) return null;
                            canvas.width = w; canvas.height = h; ctx.drawImage(video, 0, 0, w, h);
                            var r = jsQR(ctx.getImageData(0, 0, w, h).data, w, h, { inversionAttempts: 'dontInvert' });
                            return r ? r.data : null;
                        });
                    };
                (function tick() {
                    if (!stream) return;
                    decode().then(function (code) { if (code) lookup(code); }).catch(function () {})
                        .then(function () { setTimeout(tick, 180); });
                })();
            }).catch(function () { say('Không mở được camera: hãy cho phép trình duyệt dùng camera.', false); });
        });

        window.addEventListener('pagehide', stopCamera);
        input.focus();
    });
})();

/*
 * Slider trang chủ: khung cuộn ngang có bắt điểm (CSS scroll-snap), JS chỉ thêm nút trước/sau, chấm và tự chạy.
 * Tự chạy 5 giây/ảnh; dừng khi rê chuột, khi đang chạm, khi tab ẩn, hoặc khi người dùng bật "giảm chuyển động".
 */
(function () {
    document.querySelectorAll('[data-gg-slider]').forEach(function (root) {
        var track = root.querySelector('[data-track]'), slides = track.children, dots = root.querySelectorAll('[data-dots] button');
        var n = slides.length, timer = null, paused = false;
        if (n < 2) return;
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function current() { return Math.round(track.scrollLeft / track.clientWidth); }
        function go(i) { track.scrollTo({ left: ((i + n) % n) * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' }); }
        function mark() {
            var c = current();
            dots.forEach(function (d, i) { if (i === c) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
        }
        function start() { stop(); if (!reduce) timer = setInterval(function () { if (!paused && !document.hidden) go(current() + 1); }, 5000); }
        function stop() { if (timer) clearInterval(timer); timer = null; }
        root.querySelector('[data-prev]').addEventListener('click', function () { go(current() - 1); start(); });
        root.querySelector('[data-next]').addEventListener('click', function () { go(current() + 1); start(); });
        dots.forEach(function (d, i) { d.addEventListener('click', function () { go(i); start(); }); });
        var t; track.addEventListener('scroll', function () { clearTimeout(t); t = setTimeout(mark, 80); }, { passive: true });
        root.addEventListener('mouseenter', function () { paused = true; });
        root.addEventListener('mouseleave', function () { paused = false; });
        root.addEventListener('touchstart', function () { paused = true; }, { passive: true });
        root.addEventListener('touchend', function () { setTimeout(function () { paused = false; }, 4000); }, { passive: true });
        track.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); go(current() + 1); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); go(current() - 1); }
        });
        start();
    });
})();

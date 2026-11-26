/*
 * Khung trượt ngang tự chạy dùng chung (CSS scroll-snap; không có JS vẫn vuốt / cuộn được):
 *  - [data-gg-slider]   slide ảnh đầu trang chủ: mỗi lần trượt 1 ảnh, 5 giây/ảnh.
 *  - [data-gg-carousel] dải "Sản phẩm hot": mỗi lần trượt 1 thẻ cây, 3,5 giây/lần; tới cuối thì quay về đầu.
 * Luôn tự chạy, kể cả khi máy bật "giảm chuyển động" (khi đó chuyển ảnh tức thì, không lướt) và kể cả khi chuột nằm trên slide:
 * trước đây hai trường hợp này làm slide đứng yên, chủ shop tưởng hỏng. Người xem dừng bằng nút ⏸ (yêu cầu trợ năng),
 * và tự chạy tạm nghỉ khi đang chạm / đang dùng bàn phím trong khung / tab bị ẩn.
 * Dải sản phẩm nghỉ thêm khi rê chuột lên một thẻ (để kịp bấm "Thêm vào giỏ").
 */
(function () {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function setup(root, opts) {
        var track = root.querySelector('[data-track]'), items = track.children;
        var dots = root.querySelectorAll('[data-dots] button'), toggle = root.querySelector('[data-toggle]');
        if (items.length < 2) return;
        var timer = null, stopped = false, touching = false, hovering = false;

        function step() { return opts.byItem ? items[1].offsetLeft - items[0].offsetLeft : track.clientWidth; }
        function maxLeft() { return track.scrollWidth - track.clientWidth; }
        function index() { return Math.round(track.scrollLeft / Math.max(1, step())); }
        function count() { return opts.byItem ? Math.round(maxLeft() / Math.max(1, step())) + 1 : items.length; }
        function go(i) {
            var n = count(); if (n < 2) return;
            var left = (((i % n) + n) % n) * step();
            track.scrollTo({ left: Math.min(left, maxLeft()), behavior: reduce ? 'auto' : 'smooth' });
        }
        function next() { index() >= count() - 1 || track.scrollLeft >= maxLeft() - 2 ? go(0) : go(index() + 1); }
        function mark() {
            var c = index();
            dots.forEach(function (d, i) { if (i === c) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
        }
        // Đang dùng bàn phím trong khung (Tab / mũi tên): không tự trượt khỏi chỗ người xem. Bấm chuột thì không tính.
        function keyboardInside() { var a = document.activeElement; return !!a && track.contains(a) && a.matches(':focus-visible'); }
        function tick() {
            if (stopped || touching || keyboardInside() || document.hidden || (opts.hoverPause && hovering)) return;
            next();
        }
        function restart() { if (timer) clearInterval(timer); timer = setInterval(tick, opts.interval); }

        root.querySelectorAll('[data-prev]').forEach(function (b) { b.addEventListener('click', function () { go(index() - 1); restart(); }); });
        root.querySelectorAll('[data-next]').forEach(function (b) { b.addEventListener('click', function () { next(); restart(); }); });
        dots.forEach(function (d, i) { d.addEventListener('click', function () { go(i); restart(); }); });
        toggle && toggle.addEventListener('click', function () {
            stopped = !stopped;
            toggle.setAttribute('aria-pressed', stopped ? 'true' : 'false');
            toggle.setAttribute('aria-label', stopped ? 'Cho chạy tiếp' : 'Tạm dừng tự chạy');
            toggle.querySelector('i').className = 'fa ' + (stopped ? 'fa-play' : 'fa-pause');
            restart();
        });
        var t; track.addEventListener('scroll', function () { clearTimeout(t); t = setTimeout(mark, 80); }, { passive: true });
        if (opts.hoverPause) {
            track.addEventListener('mouseover', function (e) { hovering = !!e.target.closest('.card'); });
            track.addEventListener('mouseleave', function () { hovering = false; });
        }
        track.addEventListener('touchstart', function () { touching = true; }, { passive: true });
        track.addEventListener('touchend', function () { setTimeout(function () { touching = false; restart(); }, 3000); }, { passive: true });
        track.addEventListener('keydown', function (e) {
            if (e.target !== track) return;
            if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); go(index() - 1); }
        });
        restart();
    }

    document.querySelectorAll('[data-gg-slider]').forEach(function (root) { setup(root, { byItem: false, interval: 5000, hoverPause: false }); });
    document.querySelectorAll('[data-gg-carousel]').forEach(function (root) { setup(root, { byItem: true, interval: 3500, hoverPause: true }); });
})();

<!DOCTYPE html>
{{--
    Trang in tem QR dán chậu cho MÁY IN TEM NHIỆT: mỗi tem = 1 trang 50×30 mm (@page), không phụ thuộc CDN.
    QR vẽ ngay trên trình duyệt bằng public/js/vendor/qrcode-generator (tự host); nội dung QR = mã chậu 8 ký tự.
--}}
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · GreenGarden</title>
    <style>
        @page { size: 50mm 30mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #eef2ee; }
        .bar { position: sticky; top: 0; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 12px 16px; background: #1b5e20; color: #fff; z-index: 2; }
        .bar h1 { font-size: 16px; margin: 0; flex: 1 1 260px; }
        .bar button { font: inherit; font-weight: 700; padding: 8px 18px; border: 0; border-radius: 8px; background: #fff; color: #1b5e20; cursor: pointer; }
        .bar a { color: #c8e6c9; }
        .tip { padding: 10px 16px; font-size: 13px; color: #333; }
        .sheet { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 16px 24px; }
        .label { width: 50mm; height: 30mm; padding: 1.5mm; background: #fff; display: flex; gap: 1.5mm; align-items: center; overflow: hidden; outline: 1px dashed #9e9e9e; }
        .qr { width: 27mm; height: 27mm; flex: 0 0 27mm; }
        .qr svg { width: 100%; height: 100%; display: block; }
        .txt { min-width: 0; display: flex; flex-direction: column; gap: .6mm; }
        .shop { font-size: 5.5pt; letter-spacing: .3px; text-transform: uppercase; }
        .name { font-size: 7pt; font-weight: 700; line-height: 1.15; max-height: 3.2em; overflow: hidden; }
        .code { font-size: 8.5pt; font-weight: 700; font-family: "Courier New", monospace; white-space: nowrap; }
        .sku { font-size: 5.5pt; }
        .empty { padding: 40px 16px; text-align: center; }
        @media print {
            body { background: #fff; }
            .bar, .tip { display: none; }
            .sheet { display: block; padding: 0; }
            .label { outline: 0; page-break-after: always; break-after: page; }
            .label:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>
<div class="bar">
    <h1>{{ $title }} · {{ $units->count() }} tem</h1>
    @if($units->isNotEmpty())<button type="button" onclick="window.print()">In tem</button>@endif
    <a href="{{ url()->previous() }}">Quay lại</a>
</div>
<p class="tip">Trong hộp thoại in: chọn máy in tem, khổ giấy <strong>50 × 30 mm</strong>, lề <strong>Không (None)</strong>, tỉ lệ <strong>100%</strong>, bỏ "Đầu trang và chân trang".
    Máy in tem thường cần cài đúng khổ giấy trong driver trước.</p>
@if($units->isEmpty())
    <p class="empty">Không có chậu nào để in.</p>
@else
    <div class="sheet" data-testid="labels">
        @foreach($units as $u)
            <div class="label">
                <div class="qr" data-qr="{{ $u->code }}"></div>
                <div class="txt">
                    <div class="shop">GreenGarden</div>
                    <div class="name">{{ $u->plant->name }}</div>
                    <div class="code">{{ $u->displayCode() }}</div>
                    <div class="sku">{{ $u->plant->sku }}</div>
                </div>
            </div>
        @endforeach
    </div>
@endif
<script src="{{ asset('js/vendor/qrcode-generator-2.0.4.js') }}"></script>
<script>
    // Mức sửa lỗi M (~15%): tem nhiệt bị xước nhẹ vẫn đọc được; ô vuông đủ to cho máy quét rẻ
    document.querySelectorAll('[data-qr]').forEach(function (el) {
        if (!window.qrcode) { el.textContent = el.dataset.qr; return; }
        var qr = qrcode(0, 'M'); qr.addData(el.dataset.qr); qr.make();
        el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    });
</script>
</body>
</html>

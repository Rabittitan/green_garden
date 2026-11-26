# Thư viện JS bên thứ ba (tự host, không qua CDN)

| File | Gói npm | Giấy phép | Dùng ở |
|---|---|---|---|
| `qrcode-generator-2.0.4.js` | `qrcode-generator@2.0.4` (Kazuhiko Arase) | MIT (ghi ở đầu file) | Trang in tem QR dán chậu (`public/js/labels.js`) |
| `jsqr-1.4.0.js` | `jsqr@1.4.0` (Cosmo Wolfe) | Apache-2.0 (`jsqr-1.4.0.LICENSE.txt`) | Quét QR bằng camera điện thoại (`public/js/scanner.js`), chỉ tải khi bấm "Quét bằng camera" |

Lấy nguyên file `dist/` trong gói npm, không sửa. Nâng phiên bản: tải gói mới bằng `npm pack`, thay file, đổi tên theo phiên bản.

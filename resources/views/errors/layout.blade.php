{{-- Trang lỗi ĐỘC LẬP: không dùng layout chính (layout cần DB: user, giỏ hàng) để vẫn hiện được khi DB lỗi. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') - @yield('title') | GreenGarden</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f8faf8; color: #263238;
               display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 16px; }
        .box { max-width: 520px; text-align: center; }
        .code { font-size: 72px; font-weight: 800; color: #2e7d32; margin: 0; }
        h1 { font-size: 22px; margin: 8px 0 12px; }
        p { color: #546e7a; line-height: 1.6; }
        a { display: inline-block; margin-top: 16px; background: #2e7d32; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; }
    </style>
</head>
<body>
<div class="box">
    <p class="code">@yield('code')</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <a href="{{ url('/') }}">Về trang chủ</a>
</div>
</body>
</html>

{{--
    Thông báo NỔI (toast) cho flash message: success / warning / error (+ lỗi nhập liệu ở trang quản trị).
    Nổi trên nội dung, không đẩy trang; tự ẩn (thành công 4 giây, cảnh báo / lỗi 8 giây), rê chuột thì dừng đếm; có nút đóng.
    Không cần JS vẫn hiện (chỉ không tự ẩn). public/js/toasts.js: tự ẩn + giữ chỗ cuộn sau khi gửi form quay lại cùng trang.
    Biến: $withErrors (bool) — gom $errors thành 1 thông báo (trang quản trị; trang khách báo lỗi ngay tại ô nhập).
--}}
@php
    $toasts = array_filter([
        ['success', session('success'), 'fa-circle-check', 4000],
        ['warning', session('warning'), 'fa-circle-info', 8000],
        ['error', session('error'), 'fa-triangle-exclamation', 8000],
    ], fn ($t) => filled($t[1]));
@endphp
<div class="gg-toasts" data-toasts>
    @foreach($toasts as [$type, $message, $icon, $ttl])
        <div class="gg-toast {{ $type }}" role="{{ $type === 'success' ? 'status' : 'alert' }}" data-toast data-ttl="{{ $ttl }}">
            <i class="fa {{ $icon }}" aria-hidden="true"></i>
            <div class="gg-toast-text">{{ $message }}</div>
            <button type="button" class="gg-toast-close" data-toast-close aria-label="Đóng thông báo">&times;</button>
        </div>
    @endforeach
    @if(($withErrors ?? false) && $errors->any())
        <div class="gg-toast error" role="alert" data-toast data-ttl="10000">
            <i class="fa fa-triangle-exclamation" aria-hidden="true"></i>
            <div class="gg-toast-text">Kiểm tra lại thông tin:<ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            <button type="button" class="gg-toast-close" data-toast-close aria-label="Đóng thông báo">&times;</button>
        </div>
    @endif
</div>

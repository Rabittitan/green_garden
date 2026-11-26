{{--
    Ô quét mã chậu (public/js/scanner.js). Biến: $label (chữ trên ô), $id (tùy chọn).
    Máy quét USB gõ mã + Enter vào ô; điện thoại bấm "Quét bằng camera". Trang dùng nghe sự kiện "gg:scan".
--}}
<div class="gg-scanner" data-scanner data-check-url="{{ route('admin.units.check') }}"
     data-jsqr-src="{{ asset('js/vendor/jsqr-1.4.0.js') }}" @isset($id) id="{{ $id }}" @endisset>
    <label class="form-label fw-semibold mb-1" for="{{ ($id ?? 'scan').'-input' }}"><i class="fa fa-qrcode"></i> {{ $label ?? 'Quét mã chậu' }}</label>
    <div class="d-flex flex-wrap gap-2">
        <input id="{{ ($id ?? 'scan').'-input' }}" type="text" class="form-control gg-scan-input" data-scan-input autocomplete="off" autocapitalize="characters"
               spellcheck="false" inputmode="text" placeholder="Bấm vào đây rồi quét, hoặc gõ mã + Enter">
        <button type="button" class="btn btn-outline-success" data-scan-camera><i class="fa fa-camera"></i> <span>Quét bằng camera</span></button>
    </div>
    <div class="gg-scan-video" data-scan-video-box hidden><video playsinline muted></video><div class="gg-scan-aim" aria-hidden="true"></div></div>
    <div class="gg-scan-status" data-scan-status role="status" aria-live="polite"></div>
</div>
@once
    @push('scripts')
        <script src="{{ asset('js/scanner.js') }}?v={{ @filemtime(public_path('js/scanner.js')) }}"></script>
    @endpush
@endonce

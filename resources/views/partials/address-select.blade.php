{{--
    Chọn Tỉnh/thành → Phường/xã theo địa giới từ 01/07/2025 (K9). Cấp huyện không còn: server tự ghi tên tỉnh vào cột district.
    Tham số: $province, $ward (giá trị hiện tại / old()), $col (lớp cột, mặc định col-md-6).
    Không có JS: danh sách xã của tỉnh đang chọn vẫn có sẵn; đổi tỉnh thì public/js/address.js tải lại danh sách xã.
--}}
@php
    $provinces = \App\Support\VnDivisions::provinces();
    $province = (string) ($province ?? '');
    $ward = (string) ($ward ?? '');
    $pOk = in_array($province, $provinces, true);
    $wards = $pOk ? \App\Support\VnDivisions::wards($province) : [];
    $stale = $province !== '' && ! ($pOk && in_array($ward, $wards, true));     // địa chỉ lưu theo địa giới cũ
    $col = $col ?? 'col-md-6';
@endphp
<div class="row" data-gg-address="{{ asset(\App\Support\VnDivisions::PATH) }}?v={{ @filemtime(public_path(\App\Support\VnDivisions::PATH)) }}">
    <div class="{{ $col }} mb-3">
        <label class="form-label fw-semibold" for="f-province">Tỉnh / Thành phố <span class="text-danger">*</span></label>
        <select id="f-province" name="province" data-field="province" data-gg-province required class="form-select @error('province') is-invalid @enderror">
            <option value="">-- Chọn tỉnh / thành phố --</option>
            @foreach($provinces as $p)
                <option value="{{ $p }}" @selected($pOk && $p === $province)>{{ $p }}</option>
            @endforeach
        </select>
        @error('province') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="{{ $col }} mb-3">
        <label class="form-label fw-semibold" for="f-ward">Phường / Xã <span class="text-danger">*</span></label>
        <select id="f-ward" name="ward" data-field="ward" data-gg-ward required @disabled(! $pOk) class="form-select @error('ward') is-invalid @enderror">
            <option value="">{{ $pOk ? '-- Chọn phường / xã --' : '-- Chọn tỉnh / thành phố trước --' }}</option>
            @foreach($wards as $w)
                <option value="{{ $w }}" @selected($w === $ward)>{{ $w }}</option>
            @endforeach
        </select>
        @error('ward') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12" data-gg-address-note @unless($stale) hidden @endunless>
        <div class="alert alert-warning small py-2 mb-3"><i class="fa fa-circle-info"></i>
            Địa chỉ đã lưu (<span data-gg-old>{{ $ward }}{{ $ward !== '' ? ', ' : '' }}{{ $province }}</span>) theo địa giới cũ trước 01/07/2025.
            Vui lòng chọn lại tỉnh/thành và phường/xã theo danh mục mới.</div>
    </div>
</div>

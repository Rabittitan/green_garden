@extends('layouts.admin')
@section('title', 'Lập phiếu báo lỗi')

@section('content')
<a href="{{ route('admin.defect-reports.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Phiếu báo lỗi</a>
<h4 class="fw-bold mt-2 mb-1">Lập phiếu báo lỗi</h4>
<p class="small text-muted">Quét tem QR của <strong>từng chậu lỗi</strong> đang trong kho rồi chọn loại lỗi. Gửi phiếu xong, quản trị viên duyệt thì chậu mới bị xuất hủy.</p>

<form method="POST" action="{{ route('admin.defect-reports.store') }}" enctype="multipart/form-data" class="row g-3" data-defect-form>
    @csrf
    <div class="col-lg-7">
        <div class="gg-panel gg-panel-body">
            <div class="row g-2 align-items-end mb-2">
                <div class="col-sm-6">
                    <label class="form-label small mb-1" for="default-type">Loại lỗi gán cho chậu vừa quét</label>
                    <select id="default-type" class="form-select" data-default-type>
                        @foreach(\App\Models\DefectReportItem::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
            @include('admin.partials.scanner', ['label' => 'Quét tem chậu lỗi', 'id' => 'defect-scan'])
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Chậu</th><th>Loại lỗi</th><th>Ghi chú</th><th></th></tr></thead>
                    <tbody data-defect-rows>
                        @foreach(old('lines', []) as $code => $line)
                            <tr data-code="{{ $code }}"><td class="text-nowrap"><code>{{ $code }}</code></td>
                                <td><select name="lines[{{ $code }}][type]" class="form-select form-select-sm">
                                    @foreach(\App\Models\DefectReportItem::TYPES as $k => $label)<option value="{{ $k }}" @selected(($line['type'] ?? '') === $k)>{{ $label }}</option>@endforeach
                                </select></td>
                                <td><input name="lines[{{ $code }}][note]" value="{{ $line['note'] ?? '' }}" maxlength="255" class="form-control form-control-sm"></td>
                                <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove>Bỏ</button></td></tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="small text-muted mt-2 mb-0" data-defect-empty @if(old('lines')) hidden @endif>Chưa quét chậu nào.</p>
            </div>
            @error('lines') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-lg-5">
        <div class="gg-panel gg-panel-body">
            <label class="form-label fw-semibold" for="note">Mô tả chung</label>
            <textarea id="note" name="note" rows="3" maxlength="1000" class="form-control" placeholder="VD: lô Kim Tiền nhập ngày 20/11 bị úng rễ">{{ old('note') }}</textarea>
            <label class="form-label fw-semibold mt-3" for="photos">Ảnh chụp (tối đa {{ \App\Services\DefectReportService::MAX_PHOTOS }}, JPG/PNG/WEBP ≤ 4MB)</label>
            <input id="photos" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" capture="environment"
                   class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
            @error('photos') <div class="invalid-feedback">{{ $message }}</div> @enderror
            @error('photos.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <button class="btn btn-danger w-100 mt-3"><i class="fa fa-paper-plane"></i> Gửi phiếu chờ duyệt</button>
        </div>
    </div>
</form>
<template data-type-options>@foreach(\App\Models\DefectReportItem::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</template>
@endsection

@push('scripts')
<script>
    // Mỗi chậu quét (đang trong kho, chưa có trong phiếu) -> 1 dòng: chọn loại lỗi + ghi chú; server kiểm lại khi gửi
    (function () {
        var form = document.querySelector('[data-defect-form]'), scanner = document.getElementById('defect-scan');
        var rows = form.querySelector('[data-defect-rows]'), empty = form.querySelector('[data-defect-empty]');
        var options = document.querySelector('[data-type-options]').innerHTML, def = form.querySelector('[data-default-type]');
        function refresh() { empty.hidden = rows.children.length > 0; }
        rows.addEventListener('click', function (e) { if (e.target.matches('[data-remove]')) { e.target.closest('tr').remove(); refresh(); } });
        scanner.addEventListener('gg:scan', function (e) {
            if (!e.detail.ok) return;
            var u = e.detail.unit;
            if (rows.querySelector('tr[data-code="' + u.code + '"]')) return scanner.ggAccept(false, 'Chậu ' + u.display + ' đã có trong phiếu.');
            if (u.status !== 'in_stock') return scanner.ggAccept(false, 'Chậu ' + u.display + ' đang "' + u.status_label + '", chỉ báo lỗi chậu đang trong kho.');
            var tr = document.createElement('tr'); tr.dataset.code = u.code;
            tr.innerHTML = '<td class="text-nowrap"><code></code><div class="small text-muted"></div></td>'
                + '<td><select class="form-select form-select-sm">' + options + '</select></td>'
                + '<td><input maxlength="255" class="form-control form-control-sm" placeholder="Tình trạng cụ thể"></td>'
                + '<td><button type="button" class="btn btn-sm btn-link text-danger" data-remove>Bỏ</button></td>';
            tr.querySelector('code').textContent = u.display; tr.querySelector('.small').textContent = u.plant_name;
            var sel = tr.querySelector('select'); sel.name = 'lines[' + u.code + '][type]'; sel.value = def.value;
            tr.querySelector('input').name = 'lines[' + u.code + '][note]';
            rows.prepend(tr); refresh();
            scanner.ggAccept(true, 'Đã thêm ' + u.display + ' · ' + u.plant_name);
        });
        refresh();
    })();
</script>
@endpush

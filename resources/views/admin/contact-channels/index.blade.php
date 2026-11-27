@extends('layouts.admin')
@section('title', 'Kênh liên hệ')

@section('content')
<h4 class="text-green fw-bold mb-2"><i class="fa fa-comment-dots"></i> Kênh liên hệ</h4>
<p class="small text-muted">Khách thấy một nút tròn ở góc phải dưới mọi trang. Bấm vào sẽ hiện các kênh <strong>đang bật</strong> bên dưới.
    Để trống link = bỏ kênh đó. Chưa có kênh nào bật thì nút không hiện.</p>

<form method="POST" action="{{ route('admin.contact-channels.update') }}" class="gg-panel gg-panel-body" style="max-width: 760px">
    @csrf @method('PUT')
    @foreach(\App\Models\ContactChannel::CHANNELS as $key => $meta)
        @php $row = $saved->get($key); @endphp
        <div class="d-flex gap-3 align-items-start py-3 {{ $loop->last ? '' : 'border-bottom' }}">
            <span class="gg-social-icon-lg">@include('partials.social-icon', ['channel' => $key])</span>
            <div class="flex-grow-1">
                <label class="form-label fw-semibold mb-1" for="ch-{{ $key }}">{{ $meta['label'] }}</label>
                <input id="ch-{{ $key }}" name="channels[{{ $key }}][url]" type="url" maxlength="255" placeholder="{{ $meta['placeholder'] }}"
                       value="{{ old("channels.{$key}.url", $row?->url) }}" class="form-control @error("channels.{$key}.url") is-invalid @enderror">
                @error("channels.{$key}.url") <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="d-flex flex-wrap gap-3 align-items-center mt-2 small">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="channels[{{ $key }}][is_active]" value="1" id="on-{{ $key }}"
                               @checked(old("channels.{$key}.is_active", $row?->is_active ?? true))>
                        <label class="form-check-label" for="on-{{ $key }}">Hiện trên bong bóng</label>
                    </div>
                    <label class="d-flex align-items-center gap-2 mb-0">Thứ tự
                        <input name="channels[{{ $key }}][sort_order]" type="number" min="0" max="99" class="form-control form-control-sm" style="width: 70px"
                               value="{{ old("channels.{$key}.sort_order", $row?->sort_order ?? $loop->index) }}"></label>
                    <span class="text-muted">Tên miền hợp lệ: {{ implode(', ', \App\Models\ContactChannel::HOSTS[$key]) }}</span>
                </div>
            </div>
        </div>
    @endforeach
    <button class="btn btn-green mt-3"><i class="fa fa-floppy-disk"></i> Lưu</button>
</form>
@endsection

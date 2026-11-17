@foreach(['code' => 'Mã (VD: NCC-001) *', 'name' => 'Tên *', 'contact_name' => 'Người liên hệ', 'phone' => 'Điện thoại', 'email' => 'Email', 'address' => 'Địa chỉ', 'tax_code' => 'Mã số thuế'] as $f => $label)
    <div class="mb-2">
        <input name="{{ $f }}" value="{{ old($f, $supplier?->{$f}) }}" placeholder="{{ $label }}" class="form-control @error($f) is-invalid @enderror">
        @error($f) <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
@endforeach
<textarea name="note" rows="2" class="form-control mb-2" placeholder="Ghi chú">{{ old('note', $supplier?->note) }}</textarea>

{{-- Biến: $member (User). Tạo mới: có email + mật khẩu bắt buộc; sửa: mật khẩu để trống = giữ nguyên --}}
@php $granted = old('permissions', $member->permissions ?? \App\Enums\StaffPermission::values()); @endphp
<div class="row g-4">
    <div class="col-lg-6">
        <div class="gg-panel gg-panel-body">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="name">Họ tên *</label>
                <input id="name" name="name" maxlength="100" required value="{{ old('name', $member->name) }}" class="form-control @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @if($member->exists)
                <div class="mb-3"><label class="form-label fw-semibold">Email</label><div class="form-control-plaintext">{{ $member->email }}</div></div>
            @else
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="email">Email đăng nhập *</label>
                    <input type="email" id="email" name="email" required value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif
            <div class="mb-3">
                <label class="form-label fw-semibold" for="password">{{ $member->exists ? 'Mật khẩu mới (để trống = giữ nguyên)' : 'Mật khẩu *' }}</label>
                <input type="password" id="password" name="password" autocomplete="new-password" @unless($member->exists) required @endunless class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Ít nhất 10 ký tự, có cả chữ và số.</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="password_confirmation">Nhập lại mật khẩu</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
            </div>
            @if($member->exists)
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $member->is_active))>
                    <label class="form-check-label" for="is_active">Đang hoạt động (bỏ tick = khóa tài khoản, đăng xuất ngay)</label>
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="gg-panel gg-panel-body">
            <div class="fw-semibold mb-2">Được dùng các mục</div>
            @foreach(\App\Enums\StaffPermission::cases() as $perm)
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->value }}" id="perm-{{ $perm->value }}" @checked(in_array($perm->value, $granted, true))>
                    <label class="form-check-label" for="perm-{{ $perm->value }}">{{ $perm->label() }}</label>
                </div>
            @endforeach
            @error('permissions.*') <div class="text-danger small">{{ $message }}</div> @enderror
            <button class="btn btn-green mt-3"><i class="fa fa-floppy-disk"></i> Lưu</button>
            <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary mt-3">Hủy</a>
        </div>
    </div>
</div>

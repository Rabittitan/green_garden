@extends('layouts.admin')
@section('title', 'Nhân viên')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h4 class="text-green fw-bold mb-0"><i class="fa fa-id-badge"></i> Nhân viên</h4>
    <a href="{{ route('admin.staff.create') }}" class="btn btn-green"><i class="fa fa-user-plus"></i> Thêm nhân viên</a>
</div>
<p class="small text-muted">Nhân viên chỉ thấy và dùng được các mục được tick. Việc về tiền và chính sách (hoàn tiền, xác nhận chuyển khoản,
    duyệt yêu cầu nhập, duyệt flash sale, mã giảm giá, hạng, khách hàng, nhân viên) luôn chỉ dành cho quản trị viên.</p>
<div class="table-responsive">
<table class="table bg-white shadow-sm align-middle gg-table-stack">
    <thead class="table-success"><tr><th>Tài khoản</th><th>Vai trò</th><th>Quyền</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
        @foreach($people as $p)
            <tr>
                <td data-label="Tài khoản"><strong>{{ $p->name }}</strong><div class="small text-muted">{{ $p->email }}</div></td>
                <td data-label="Vai trò"><span class="badge {{ $p->isAdmin() ? 'bg-dark' : 'bg-success' }}">{{ $p->role->label() }}</span></td>
                <td data-label="Quyền" class="small">
                    @if($p->isAdmin()) Tất cả
                    @elseif($p->permissions === null) Tất cả quyền nhân viên <span class="text-muted">(tài khoản cũ)</span>
                    @elseif($p->permissions === []) <span class="text-danger">Chưa được cấp quyền nào</span>
                    @else {{ collect($p->permissions)->map(fn ($k) => \App\Enums\StaffPermission::from($k)->label())->implode(', ') }}
                    @endif
                </td>
                <td data-label="Trạng thái">{!! $p->is_active ? '<span class="badge bg-success">Hoạt động</span>' : '<span class="badge bg-danger">Đã khóa</span>' !!}</td>
                <td data-label="">@unless($p->isAdmin())<a href="{{ route('admin.staff.edit', $p) }}" class="btn btn-sm btn-outline-success text-nowrap"><i class="fa fa-pen"></i> Sửa quyền</a>@endunless</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
@endsection

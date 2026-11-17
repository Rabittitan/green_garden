@extends('layouts.admin')
@section('title', 'Nhà cung cấp')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-handshake"></i> Nhà cung cấp</h4>
<div class="row g-4">
    <div class="col-lg-8">
        <table class="table bg-white shadow-sm align-middle">
            <thead class="table-success"><tr><th>Mã</th><th>Tên</th><th>Liên hệ</th><th>MST</th><th class="text-end">Phiếu nhập</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
                @forelse($suppliers as $s)
                    <tr>
                        <td class="fw-bold">{{ $s->code }}</td>
                        <td>{{ $s->name }}<div class="small text-muted">{{ $s->address }}</div></td>
                        <td class="small">{{ $s->contact_name }}<br>{{ $s->phone }}<br>{{ $s->email }}</td>
                        <td>{{ $s->tax_code }}</td>
                        <td class="text-end">{{ $s->goods_receipts_count }}</td>
                        <td>{!! $s->is_active ? '<span class="badge bg-success">Hợp tác</span>' : '<span class="badge bg-secondary">Ngừng</span>' !!}</td>
                        <td><a href="{{ route('admin.suppliers.edit', $s) }}" class="btn btn-sm btn-warning"><i class="fa fa-pen"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có nhà cung cấp.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $suppliers->links() }}
    </div>
    <div class="col-lg-4">
        <form action="{{ route('admin.suppliers.store') }}" method="POST" class="card card-body shadow-sm">@csrf
            <h6 class="fw-bold">Thêm nhà cung cấp</h6>
            @include('admin.suppliers._fields', ['supplier' => null])
            <button class="btn btn-green">Thêm</button>
        </form>
    </div>
</div>
@endsection

@extends('layouts.admin')
@section('title', 'Quản lý cây cảnh')

@section('content')
<div class="d-flex justify-content-between mb-3">
    <h4 class="text-green fw-bold"><i class="fa fa-leaf"></i> Quản lý cây cảnh</h4>
    <a href="{{ route('admin.plants.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Thêm cây mới</a>
</div>

<form class="mb-3 d-flex gap-2" method="GET">
    <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:300px" placeholder="Tên / SKU">
    <button class="btn btn-sm btn-outline-secondary">Tìm</button>
</form>
<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success">
        <tr><th>Ảnh</th><th>SKU</th><th>Tên cây</th><th>Danh mục</th><th>Giá</th><th>Tồn kho</th><th>Mệnh</th><th>Ánh sáng</th><th style="width:140px">Thao tác</th></tr>
    </thead>
    <tbody>
        @foreach($plants as $plant)
            <tr>
                <td><img src="{{ $plant->image }}" width="50" height="50" class="rounded" style="object-fit:cover"
                         onerror="this.onerror=null;this.src='https://placehold.co/50x50/e8f5e9/2e7d32?text=GL'"></td>
                <td class="small">{{ $plant->sku }}</td>
                <td>{{ $plant->name }}
                    @if($plant->trashed()) <span class="badge bg-dark">Đã xóa</span>
                    @elseif(! $plant->is_active) <span class="badge bg-secondary">Ngừng bán</span> @endif</td>
                <td>{{ $plant->category->name }}</td>
                <td>@money($plant->price)</td>
                <td>
                    <a href="{{ route('admin.inventory.show', $plant) }}" class="badge text-decoration-none bg-{{ $plant->stock <= $plant->reorder_point ? 'danger' : 'success' }}">{{ $plant->stock }}</a>
                </td>
                <td>{{ \App\Models\Plant::ELEMENTS[$plant->element] }}</td>
                <td>{{ \App\Models\Plant::LIGHTS[$plant->light] }}</td>
                <td>
                    @unless($plant->trashed())
                    <a href="{{ route('admin.plants.edit', $plant) }}" class="btn btn-sm btn-warning"><i class="fa fa-pen"></i></a>
                    {{-- Form xóa: HTML chỉ có GET/POST nên dùng @method('DELETE') để giả lập --}}
                    <form action="{{ route('admin.plants.destroy', $plant) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Ngừng bán và ẩn cây {{ $plant->name }}? Đơn cũ vẫn giữ nguyên.')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                    </form>
                    @endunless
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $plants->links() }}
@endsection
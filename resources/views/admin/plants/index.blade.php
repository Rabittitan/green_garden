@extends('layouts.admin')
@section('title', 'Quản lý cây cảnh')

@section('content')
<div class="d-flex justify-content-between mb-3">
    <h4 class="text-green fw-bold"><i class="fa fa-leaf"></i> Quản lý cây cảnh</h4>
    <a href="{{ route('admin.plants.create') }}" class="btn btn-green"><i class="fa fa-plus"></i> Thêm cây mới</a>
</div>

<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success">
        <tr><th>Ảnh</th><th>Tên cây</th><th>Danh mục</th><th>Giá</th><th>Tồn kho</th><th>Mệnh</th><th>Ánh sáng</th><th style="width:140px">Thao tác</th></tr>
    </thead>
    <tbody>
        @foreach($plants as $plant)
            <tr>
                <td><img src="{{ $plant->image }}" width="50" height="50" class="rounded" style="object-fit:cover"
                         onerror="this.onerror=null;this.src='https://placehold.co/50x50/e8f5e9/2e7d32?text=GL'"></td>
                <td>{{ $plant->name }}</td>
                <td>{{ $plant->category->name }}</td>
                <td>{{ number_format($plant->price, 0, ',', '.') }}đ</td>
                <td>
                    <span class="badge bg-{{ $plant->stock <= 5 ? 'danger' : 'success' }}">{{ $plant->stock }}</span>
                </td>
                <td>{{ \App\Models\Plant::ELEMENTS[$plant->element] }}</td>
                <td>{{ \App\Models\Plant::LIGHTS[$plant->light] }}</td>
                <td>
                    <a href="{{ route('admin.plants.edit', $plant->id) }}" class="btn btn-sm btn-warning"><i class="fa fa-pen"></i></a>
                    {{-- Form xóa: HTML chỉ có GET/POST nên dùng @method('DELETE') để giả lập --}}
                    <form action="{{ route('admin.plants.destroy', $plant->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Xóa cây {{ $plant->name }}?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $plants->links() }}
@endsection
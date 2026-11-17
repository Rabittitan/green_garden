@extends('layouts.admin')
@section('title', 'Kho')

@section('content')
<div class="d-flex justify-content-between mb-3">
    <h4 class="text-green fw-bold"><i class="fa fa-warehouse"></i> Kho</h4>
    <div>
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-sm {{ request('low') ? 'btn-outline-dark' : 'btn-dark' }}">Tất cả</a>
        <a href="{{ route('admin.inventory.index', ['low' => 1]) }}" class="btn btn-sm {{ request('low') ? 'btn-danger' : 'btn-outline-danger' }}">Dưới ngưỡng</a>
    </div>
</div>
<p class="text-muted small">Tồn kho chỉ thay đổi qua sổ kho: bán / hủy / hoàn hàng, nhập hàng, kiểm kê, hư hỏng. Mỗi thay đổi đều có 1 dòng trong sổ.</p>
<table class="table bg-white shadow-sm align-middle">
    <thead class="table-success"><tr><th>SKU</th><th>Cây</th><th>Danh mục</th><th class="text-end">Tồn</th><th class="text-end">Ngưỡng</th><th class="text-end">Giá vốn BQ</th><th class="text-end">Giá trị tồn</th><th></th></tr></thead>
    <tbody>
        @foreach($plants as $p)
            <tr class="{{ $p->stock <= $p->reorder_point ? 'table-warning' : '' }}">
                <td class="small">{{ $p->sku }}</td>
                <td>{{ $p->name }} @unless($p->is_active)<span class="badge bg-secondary">Ngừng bán</span>@endunless</td>
                <td>{{ $p->category->name }}</td>
                <td class="text-end fw-bold">{{ $p->stock }}</td>
                <td class="text-end">{{ $p->reorder_point }}</td>
                <td class="text-end">@money($p->cost_price)</td>
                <td class="text-end">@money($p->stock * $p->cost_price)</td>
                <td><a href="{{ route('admin.inventory.show', $p) }}" class="btn btn-sm btn-outline-success">Sổ kho / Kiểm kê</a></td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $plants->links() }}
@endsection

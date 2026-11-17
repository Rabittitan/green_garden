@extends('layouts.app')
@section('title', 'Danh sách cây cảnh')

@section('content')
<div class="row">
    {{-- ===== CỘT TRÁI: BỘ LỌC (gửi bằng GET nên URL có dạng ?category=1&price=100-300...) ===== --}}
    <div class="col-lg-3 mb-4">
        <form action="{{ route('plants.index') }}" method="GET" class="card card-body shadow-sm">
            <h5 class="text-green"><i class="fa fa-filter"></i> Bộ lọc</h5>

            <label class="mt-2 fw-semibold">Từ khóa</label>
            <input type="text" name="keyword" class="form-control" value="{{ request('keyword') }}" placeholder="Tên cây...">

            <label class="mt-3 fw-semibold">Danh mục</label>
            <select name="category" class="form-select">
                <option value="">-- Tất cả --</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>

            <label class="mt-3 fw-semibold">Khoảng giá</label>
            <select name="price" class="form-select">
                <option value="">-- Tất cả --</option>
                <option value="duoi-100" @selected(request('price') == 'duoi-100')>Dưới 100.000đ</option>
                <option value="100-300"  @selected(request('price') == '100-300')>100.000đ - 300.000đ</option>
                <option value="300-500"  @selected(request('price') == '300-500')>300.000đ - 500.000đ</option>
                <option value="tren-500" @selected(request('price') == 'tren-500')>Trên 500.000đ</option>
            </select>

            <label class="mt-3 fw-semibold">Ánh sáng</label>
            <select name="light" class="form-select">
                <option value="">-- Tất cả --</option>
                @foreach(\App\Models\Plant::LIGHTS as $key => $label)
                    <option value="{{ $key }}" @selected(request('light') == $key)>{{ $label }}</option>
                @endforeach
            </select>

            <label class="mt-3 fw-semibold">Mệnh phong thủy</label>
            <select name="element" class="form-select">
                <option value="">-- Tất cả --</option>
                @foreach(\App\Models\Plant::ELEMENTS as $key => $label)
                    <option value="{{ $key }}" @selected(request('element') == $key)>{{ $label }}</option>
                @endforeach
            </select>

            <button class="btn btn-green mt-3">Lọc cây</button>
            <a href="{{ route('plants.index') }}" class="btn btn-outline-secondary mt-2">Xóa bộ lọc</a>
        </form>
    </div>

    {{-- ===== CỘT PHẢI: KẾT QUẢ ===== --}}
    <div class="col-lg-9">
        <h4 class="text-green fw-bold mb-3">Cây cảnh <small class="text-muted fs-6">({{ $plants->total() }} sản phẩm)</small></h4>

        <div class="row g-3">
            @forelse($plants as $plant)
                <div class="col-6 col-md-4">@include('partials.plant-card', ['plant' => $plant])</div>
            @empty
                <div class="col-12"><div class="alert alert-warning">Không tìm thấy cây phù hợp. Hãy thử bỏ bớt bộ lọc.</div></div>
            @endforelse
        </div>

        {{-- Phân trang (đã giữ bộ lọc nhờ withQueryString ở Controller) --}}
        <div class="mt-4">{{ $plants->links() }}</div>
    </div>
</div>
@endsection
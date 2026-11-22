@extends('layouts.app')
@section('title', 'Danh sách cây cảnh')

@php
    // Số bộ lọc đang bật (chỉ đọc query string, không truy vấn)
    $activeFilters = collect(['keyword', 'category', 'price', 'light', 'element'])->filter(fn ($f) => request()->filled($f))->count();
@endphp

@section('content')
<div class="row g-4">
    {{-- ===== CỘT TRÁI: BỘ LỌC (gửi bằng GET nên URL có dạng ?category=1&price=100-300...) ===== --}}
    <div class="col-lg-3">
        <button class="btn btn-outline-green w-100 d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#bo-loc" aria-expanded="false">
            <i class="fa fa-sliders me-1"></i> Bộ lọc @if($activeFilters)<span class="badge bg-success ms-1">{{ $activeFilters }}</span>@endif
        </button>
        <div class="collapse d-lg-block mt-3 mt-lg-0" id="bo-loc">
            <form action="{{ route('plants.index') }}" method="GET" class="card card-body shadow-sm gg-filter">
                <h5 class="fw-bold mb-0"><i class="fa fa-filter text-green"></i> Bộ lọc</h5>

                <label class="form-label" for="f-keyword">Từ khóa</label>
                <input type="text" id="f-keyword" name="keyword" class="form-control" value="{{ request('keyword') }}" placeholder="Tên cây...">

                <label class="form-label" for="f-category">Danh mục</label>
                <select id="f-category" name="category" class="form-select">
                    <option value="">-- Tất cả --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="f-price">Khoảng giá</label>
                <select id="f-price" name="price" class="form-select">
                    <option value="">-- Tất cả --</option>
                    <option value="duoi-100" @selected(request('price') == 'duoi-100')>Dưới 100.000đ</option>
                    <option value="100-300"  @selected(request('price') == '100-300')>100.000đ - 300.000đ</option>
                    <option value="300-500"  @selected(request('price') == '300-500')>300.000đ - 500.000đ</option>
                    <option value="tren-500" @selected(request('price') == 'tren-500')>Trên 500.000đ</option>
                </select>

                <label class="form-label" for="f-light">Ánh sáng</label>
                <select id="f-light" name="light" class="form-select">
                    <option value="">-- Tất cả --</option>
                    @foreach(\App\Models\Plant::LIGHTS as $key => $label)
                        <option value="{{ $key }}" @selected(request('light') == $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="f-element">Mệnh phong thủy</label>
                <select id="f-element" name="element" class="form-select">
                    <option value="">-- Tất cả --</option>
                    @foreach(\App\Models\Plant::ELEMENTS as $key => $label)
                        <option value="{{ $key }}" @selected(request('element') == $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <button class="btn btn-green mt-4">Lọc cây</button>
                <a href="{{ route('plants.index') }}" class="btn btn-link text-secondary mt-1">Xóa bộ lọc</a>
            </form>
        </div>
    </div>

    {{-- ===== CỘT PHẢI: KẾT QUẢ ===== --}}
    <div class="col-lg-9">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <span class="gg-eyebrow">Cửa hàng</span>
                <h1 class="h3 fw-bold mb-0">Cây cảnh <small class="text-muted fs-6 fw-normal">({{ $plants->total() }} sản phẩm)</small></h1>
            </div>
        </div>

        <div class="row g-3">
            @forelse($plants as $plant)
                <div class="col-6 col-md-4">@include('partials.plant-card', ['plant' => $plant])</div>
            @empty
                <div class="col-12">
                    <div class="text-center bg-white border rounded-4 py-5 px-3">
                        <div class="gg-auth-icon mx-auto"><i class="fa fa-seedling"></i></div>
                        <h2 class="h5 fw-bold">Không tìm thấy cây phù hợp. Hãy thử bỏ bớt bộ lọc.</h2>
                        <a href="{{ route('plants.index') }}" class="btn btn-outline-green mt-2">Xem tất cả cây cảnh</a>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- Phân trang (đã giữ bộ lọc nhờ withQueryString ở Controller) --}}
        <div class="mt-4">{{ $plants->links() }}</div>
    </div>
</div>
@endsection

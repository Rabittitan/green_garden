@extends('layouts.app')
@section('title', $plant->name)

@section('content')
<nav class="mb-3"><a href="{{ route('plants.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Quay lại danh sách</a></nav>

<div class="row g-4">
    <div class="col-md-5">
        <img src="{{ $plant->image }}" class="img-fluid rounded-3 shadow-sm w-100" alt="{{ $plant->name }}"
             onerror="this.onerror=null;this.src='https://placehold.co/600x500/e8f5e9/2e7d32?text=GreenLife'">
    </div>
    <div class="col-md-7">
        <small class="text-muted">{{ $plant->category->name }}</small>
        <h2 class="fw-bold">{{ $plant->name }}</h2>
        <div class="fs-3 fw-bold text-green my-2">{{ number_format($plant->price, 0, ',', '.') }}đ</div>

        <table class="table table-sm w-auto">
            <tr><th class="pe-4">Mệnh phong thủy</th><td><span class="badge bg-success">{{ \App\Models\Plant::ELEMENTS[$plant->element] }}</span></td></tr>
            <tr><th>Ánh sáng</th><td>{{ \App\Models\Plant::LIGHTS[$plant->light] }}</td></tr>
            <tr><th>Tình trạng</th>
                <td>
                    @if($plant->stock > 0) <span class="text-success">Còn {{ $plant->stock }} cây</span>
                    @else <span class="text-danger">Hết hàng</span> @endif
                </td>
            </tr>
        </table>

        <p class="text-secondary">{{ $plant->description }}</p>

        @if($plant->stock > 0)
            <form action="{{ route('cart.add', $plant->id) }}" method="POST" class="d-flex gap-2 align-items-center">
                @csrf
                <input type="number" name="quantity" value="1" min="1" max="{{ $plant->stock }}" class="form-control" style="width: 90px">
                <button class="btn btn-green"><i class="fa fa-cart-plus"></i> Thêm vào giỏ hàng</button>
            </form>
        @endif
    </div>
</div>

{{-- Cây cùng mệnh --}}
@if($related->count())
    <h4 class="text-green fw-bold mt-5 mb-3">Cây cùng mệnh {{ \App\Models\Plant::ELEMENTS[$plant->element] }}</h4>
    <div class="row g-3">
        @foreach($related as $item)
            {{-- Controller chưa nạp category cho $related nên nạp thêm tại đây --}}
            @php $item->load('category'); @endphp
            <div class="col-6 col-md-3">@include('partials.plant-card', ['plant' => $item])</div>
        @endforeach
    </div>
@endif
@endsection
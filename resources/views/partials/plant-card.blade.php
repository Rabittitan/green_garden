{{-- Thẻ hiển thị 1 cây. Nhận biến $plant. onerror: ảnh lỗi thì hiện ảnh dự phòng --}}
<div class="card plant-card h-100">
    <a href="{{ route('plants.show', $plant->id) }}">
        <img src="{{ $plant->image }}" class="card-img-top" alt="{{ $plant->name }}"
             onerror="this.onerror=null;this.src='https://placehold.co/600x400/e8f5e9/2e7d32?text=GreenLife'">
    </a>
    <div class="card-body d-flex flex-column">
        <small class="text-muted">{{ $plant->category->name }}</small>
        <h6 class="card-title mt-1">
            <a href="{{ route('plants.show', $plant->id) }}" class="text-decoration-none text-dark">{{ $plant->name }}</a>
        </h6>
        <div class="mb-2">
            <span class="badge bg-success-subtle text-success border">{{ \App\Models\Plant::ELEMENTS[$plant->element] }}</span>
            <span class="badge bg-warning-subtle text-warning-emphasis border">{{ \App\Models\Plant::LIGHTS[$plant->light] }}</span>
        </div>
        <div class="mt-auto">
            <div class="fw-bold text-green fs-5 mb-2">@money($plant->price)</div>
            @if($plant->stock > 0)
                {{-- Thêm nhanh 1 cây vào giỏ --}}
                <form action="{{ route('cart.add', $plant->id) }}" method="POST">
                    @csrf
                    <button class="btn btn-green btn-sm w-100"><i class="fa fa-cart-plus"></i> Thêm vào giỏ</button>
                </form>
            @else
                <button class="btn btn-secondary btn-sm w-100" disabled>Hết hàng</button>
            @endif
        </div>
    </div>
</div>
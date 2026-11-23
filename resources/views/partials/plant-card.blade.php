{{-- Thẻ hiển thị 1 cây. Nhận biến $plant (đã nạp sẵn category), tùy chọn $sold (số đã bán gần đây: hiện nhãn "Hot"). Số sao / tồn kho lấy từ cột của cây: không truy vấn thêm.
     onerror: ảnh lỗi thì hiện ảnh dự phòng --}}
<div class="card plant-card h-100">
    <a href="{{ route('plants.show', $plant->id) }}" class="gg-card-media">
        <img src="{{ $plant->image }}" alt="{{ $plant->name }}" loading="lazy"
             onerror="this.onerror=null;this.src='https://placehold.co/600x400/e8f5e9/2e7d32?text=GreenGarden'">
        @if($plant->stock <= 0)
            <span class="gg-card-flag gg-flag-out">Hết hàng</span>
        @elseif($plant->stock <= $plant->reorder_point)
            <span class="gg-card-flag gg-flag-low">Sắp hết hàng</span>
        @endif
        @if(! empty($sold))
            <span class="gg-card-flag gg-flag-hot"><i class="fa fa-fire"></i> Đã bán {{ $sold }}</span>
        @endif
    </a>
    <div class="card-body d-flex flex-column">
        <div class="gg-card-cat">{{ $plant->category->name }}</div>
        <h3 class="gg-card-title fs-6 mt-1 mb-1">
            <a href="{{ route('plants.show', $plant->id) }}">{{ $plant->name }}</a>
        </h3>
        @if($plant->rating_count > 0)
            <div class="gg-stars mb-1" title="{{ $plant->rating_avg }}/5">
                @for($i = 1; $i <= 5; $i++)<i class="fa{{ $i <= round($plant->rating_avg) ? 's' : 'r' }} fa-star"></i>@endfor
                <span class="text-muted ms-1">({{ $plant->rating_count }})</span>
            </div>
        @endif
        <div class="d-flex flex-wrap gap-1 mb-2">
            <span class="gg-chip">{{ \App\Models\Plant::ELEMENTS[$plant->element] }}</span>
            <span class="gg-chip gg-chip-sun">{{ \App\Models\Plant::LIGHTS[$plant->light] }}</span>
        </div>
        <div class="mt-auto">
            <div class="gg-price fs-5 mb-2">@money($plant->price)</div>
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

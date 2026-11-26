@extends('layouts.app')
@section('title', $plant->name)

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-green text-decoration-none">Trang chủ</a></li>
        <li class="breadcrumb-item"><a href="{{ route('plants.index') }}" class="text-green text-decoration-none">Cây cảnh</a></li>
        <li class="breadcrumb-item"><a href="{{ route('plants.index', ['category' => $plant->category_id]) }}" class="text-green text-decoration-none">{{ $plant->category->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $plant->name }}</li>
    </ol>
</nav>

<div class="row g-4 align-items-start">
    <div class="col-md-6 col-lg-5">
        <div class="gg-gallery">
            <img src="{{ $plant->image }}" alt="{{ $plant->name }}"
                 onerror="this.onerror=null;this.src='https://placehold.co/600x500/e8f5e9/2e7d32?text=GreenGarden'">
        </div>
    </div>
    <div class="col-md-6 col-lg-7">
        <div class="gg-buybox">
            <span class="gg-eyebrow">{{ $plant->category->name }}</span>
            <h1 class="mt-1 mb-2">{{ $plant->name }}</h1>
            @if($plant->rating_count > 0)
                <div class="mb-2"><span class="gg-stars fs-6">@for($i = 1; $i <= 5; $i++)<i class="fa{{ $i <= round($plant->rating_avg) ? 's' : 'r' }} fa-star"></i>@endfor</span>
                    <strong>{{ $plant->rating_avg }}</strong>/5 · <a href="#reviews" class="text-green">{{ $plant->rating_count }} đánh giá</a></div>
            @endif
            @if($flash && $flash->sale_price < $plant->price)
                {{-- Flash sale đang chạy: giá thật vẫn do server tính lúc đặt hàng (còn đủ suất + rẻ hơn giá thường mới được giá sale) --}}
                <div class="gg-flash-box my-2">
                    <div class="d-flex flex-wrap align-items-baseline gap-2">
                        <span class="badge bg-danger"><i class="fa fa-bolt"></i> Flash sale</span>
                        <span class="gg-price text-danger">@money($flash->sale_price)</span>
                        <s class="text-muted">@money($plant->price)</s>
                        <span class="badge bg-danger-subtle text-danger">−{{ round(($plant->price - $flash->sale_price) * 100 / max(1, $plant->price)) }}%</span>
                    </div>
                    <div class="small mt-1">Kết thúc @vndt($flash->sale->ends_at)
                        @if($flash->quantity_limit) · còn <strong>{{ $flash->remaining() }}</strong>/{{ $flash->quantity_limit }} suất @endif
                        · vẫn được cộng ưu đãi hạng và mã giảm giá</div>
                </div>
            @else
                <div class="gg-price my-2">@money($plant->price)</div>
            @endif

            <div class="gg-attrs my-3">
                <div class="gg-attr"><small>Mệnh phong thủy</small><strong>{{ \App\Models\Plant::ELEMENTS[$plant->element] }}</strong></div>
                <div class="gg-attr"><small>Ánh sáng</small><strong>{{ \App\Models\Plant::LIGHTS[$plant->light] }}</strong></div>
                <div class="gg-attr"><small>Tình trạng</small>
                    @if($plant->stock > 0) <strong class="text-success">Còn {{ $plant->stock }} cây</strong>
                    @else <strong class="text-danger">Hết hàng</strong> @endif
                </div>
                @if($plant->height)<div class="gg-attr"><small>Kích thước</small><strong>{{ $plant->height }}</strong></div>@endif
                @if($plant->growth_rate)<div class="gg-attr"><small>Tốc độ lớn</small><strong>{{ \App\Models\Plant::GROWTH_RATES[$plant->growth_rate] }}</strong></div>@endif
                @if($plant->watering)<div class="gg-attr"><small>Tưới nước</small><strong>{{ $plant->watering }}</strong></div>@endif
            </div>
            @if($plant->hasDetails())
                <a href="#chi-tiet" class="small text-green d-inline-block mb-2"><i class="fa fa-book-open"></i> Xem kích thước, quá trình sinh trưởng và cách chăm sóc</a>
            @endif

            <p class="text-secondary">{{ $plant->description }}</p>

            @if($plant->stock > 0)
                <form action="{{ route('cart.add', $plant->id) }}" method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                    @csrf
                    <label class="visually-hidden" for="quantity">Số lượng</label>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="{{ $plant->stock }}" class="form-control gg-qty">
                    <button class="btn btn-green btn-lg flex-grow-1"><i class="fa fa-cart-plus"></i> Thêm vào giỏ hàng</button>
                </form>
            @else
                <button class="btn btn-secondary btn-lg w-100" disabled>Hết hàng</button>
            @endif

            {{-- Cam kết: lấy từ cấu hình shop --}}
            <ul class="gg-assure">
                <li><i class="fa fa-truck-fast"></i> Miễn phí giao hàng cho đơn từ @money(config('shop.shipping.free_min_order'))</li>
                <li><i class="fa fa-rotate-left"></i> Đổi trả trong {{ config('shop.return_window_days') }} ngày kể từ khi nhận hàng</li>
                <li><i class="fa fa-hand-holding-dollar"></i> Thanh toán khi nhận hàng (COD)</li>
            </ul>
        </div>
    </div>
</div>

{{-- ===== THÔNG TIN CHI TIẾT (K8): mục nào trống thì ẩn ===== --}}
@if($plant->hasDetails())
    @php
        // Cách chăm sóc: mỗi dòng một ý -> danh sách (bỏ gạch đầu dòng người nhập tự gõ)
        $careLines = collect(preg_split('/\R/u', (string) $plant->care_guide))
            ->map(fn ($l) => trim(preg_replace('/^[-•*+]\s*/u', '', trim($l))))->filter()->values();
        $specs = array_filter([
            'Danh mục' => $plant->category->name,
            'Kích thước cây' => $plant->height,
            'Chậu' => $plant->pot_size,
            'Tốc độ lớn' => $plant->growth_rate ? \App\Models\Plant::GROWTH_RATES[$plant->growth_rate] : null,
            'Tưới nước' => $plant->watering,
            'Ánh sáng' => \App\Models\Plant::LIGHTS[$plant->light],
            'Mệnh phong thủy' => \App\Models\Plant::ELEMENTS[$plant->element],
            'Mã sản phẩm' => $plant->sku,
        ], fn ($v) => $v !== null && $v !== '');
    @endphp
    <div class="gg-section-head" id="chi-tiet"><h2>Thông tin chi tiết</h2></div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="gg-panel gg-panel-body h-100">
                <h3 class="h6 fw-bold mb-3"><i class="fa fa-ruler-combined text-green"></i> Thông số</h3>
                <dl class="gg-specs mb-0">
                    @foreach($specs as $label => $value)
                        <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </div>
        <div class="col-lg-8 d-flex flex-column gap-4">
            @if(trim((string) $plant->growth_info) !== '')
                <div class="gg-panel gg-panel-body">
                    <h3 class="h6 fw-bold mb-2"><i class="fa fa-seedling text-green"></i> Quá trình sinh trưởng</h3>
                    <div class="text-secondary" style="white-space: pre-line">{{ trim($plant->growth_info) }}</div>
                </div>
            @endif
            @if($careLines->isNotEmpty())
                <div class="gg-panel gg-panel-body">
                    <h3 class="h6 fw-bold mb-2"><i class="fa fa-hand-holding-droplet text-green"></i> Cách chăm sóc</h3>
                    <ul class="gg-care mb-0">
                        @foreach($careLines as $line)<li>{{ $line }}</li>@endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endif

{{-- Cây cùng mệnh --}}
@if($related->count())
    <div class="gg-section-head"><h2>Cây cùng mệnh {{ \App\Models\Plant::ELEMENTS[$plant->element] }}</h2></div>
    <div class="row g-3">
        @foreach($related as $item)
            <div class="col-6 col-md-3">@include('partials.plant-card', ['plant' => $item, 'flash' => $flashMap[$item->id] ?? null])</div>
        @endforeach
    </div>
@endif

{{-- ===== ĐÁNH GIÁ ===== --}}
<div class="gg-section-head" id="reviews"><h2>Đánh giá từ người mua</h2></div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card card-body shadow-sm">
            <div class="d-flex align-items-end gap-2">
                <div class="display-5 fw-bold lh-1">{{ $plant->rating_count ? $plant->rating_avg : '–' }}</div><div class="text-muted pb-1">/5</div>
            </div>
            <div class="small text-muted mb-3">{{ $plant->rating_count }} đánh giá đã duyệt</div>
            @for($star = 5; $star >= 1; $star--)
                @php $n = (int) ($distribution[$star] ?? 0); @endphp
                <div class="d-flex align-items-center gap-2 small mb-1">
                    <span style="width:30px">{{ $star }}<i class="fa fa-star text-warning"></i></span>
                    <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-warning" style="width: {{ $plant->rating_count ? $n * 100 / $plant->rating_count : 0 }}%"></div></div>
                    <span style="width:24px" class="text-end">{{ $n }}</span>
                </div>
            @endfor

            <hr>
            @auth
                @if($canReview)
                    @if($myReview)
                        <div class="small mb-2">Đánh giá của bạn: <span class="badge bg-{{ ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$myReview->status->value] }}">{{ ['pending' => 'Chờ duyệt', 'approved' => 'Đã hiển thị', 'rejected' => 'Bị từ chối'][$myReview->status->value] }}</span>
                            @if($myReview->reject_reason)<div class="text-danger">Lý do: {{ $myReview->reject_reason }}</div>@endif</div>
                    @endif
                    <form action="{{ route('reviews.store', $plant->id) }}" method="POST">@csrf
                        <label class="form-label small fw-semibold" for="rating">Số sao</label>
                        <select id="rating" name="rating" class="form-select form-select-sm mb-2" required>
                            @for($i = 5; $i >= 1; $i--)<option value="{{ $i }}" @selected((int) old('rating', $myReview->rating ?? 5) === $i)>{{ str_repeat('★', $i) }} ({{ $i }} sao)</option>@endfor
                        </select>
                        <input name="title" class="form-control form-control-sm mb-2" maxlength="150" placeholder="Tiêu đề" value="{{ old('title', $myReview->title ?? '') }}">
                        <textarea name="content" rows="3" class="form-control form-control-sm mb-2" maxlength="2000" placeholder="Cây có như mô tả? Đóng gói thế nào?">{{ old('content', $myReview->content ?? '') }}</textarea>
                        <button class="btn btn-sm btn-green w-100">{{ $myReview ? 'Cập nhật đánh giá' : 'Gửi đánh giá' }}</button>
                    </form>
                    @if($myReview)
                        <form action="{{ route('reviews.destroy', $myReview) }}" method="POST" class="mt-1" onsubmit="return confirm('Xóa đánh giá?')">@csrf @method('DELETE')
                            <button class="btn btn-link btn-sm text-danger p-0">Xóa đánh giá của tôi</button></form>
                    @endif
                @else
                    <p class="small text-muted mb-0">Chỉ khách đã mua và nhận cây mới viết được đánh giá.</p>
                @endif
            @else
                <p class="small text-muted mb-0"><a href="{{ route('login') }}">Đăng nhập</a> để đánh giá cây bạn đã mua.</p>
            @endauth
        </div>
    </div>
    <div class="col-lg-8">
        @forelse($reviews as $r)
            <div class="gg-review">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="gg-review-avatar">{{ mb_strtoupper(mb_substr(trim($r->user->name), 0, 1)) }}</span>
                        <div><strong>{{ $r->user->name }}</strong> <span class="badge bg-success-subtle text-success"><i class="fa fa-check"></i> Đã mua hàng</span>
                            <div class="gg-stars">{{ str_repeat('★', $r->rating) }}<span class="text-muted">{{ str_repeat('★', 5 - $r->rating) }}</span></div></div>
                    </div>
                    <span class="small text-muted">@vndt($r->created_at)</span>
                </div>
                @if($r->title)<div class="fw-bold mt-2">{{ $r->title }}</div>@endif
                @if($r->content)<div style="white-space: pre-line">{{ $r->content }}</div>@endif
                @if($r->shop_reply)
                    <div class="mt-2 p-2 rounded bg-success bg-opacity-10 border-start border-success border-3 small">
                        <strong>Shop phản hồi:</strong> <span style="white-space: pre-line">{{ $r->shop_reply }}</span></div>
                @endif
                <div class="mt-2 small">
                    @auth
                        @if(auth()->id() !== $r->user_id)
                            <form action="{{ route('reviews.vote', $r) }}" method="POST" class="d-inline">@csrf
                                <button class="btn btn-sm rounded-pill {{ in_array($r->id, $votedIds) ? 'btn-success' : 'btn-outline-secondary' }}"><i class="fa fa-thumbs-up"></i> Hữu ích ({{ $r->helpful_count }})</button></form>
                        @else <span class="text-muted">{{ $r->helpful_count }} người thấy hữu ích</span> @endif
                    @else <span class="text-muted">{{ $r->helpful_count }} người thấy hữu ích</span> @endauth
                </div>
            </div>
        @empty
            <div class="text-center bg-white border rounded-4 py-4 text-muted">Chưa có đánh giá nào cho cây này.</div>
        @endforelse
        {{ $reviews->links() }}
    </div>
</div>

<div class="mt-4">
    @include('partials.comments', ['comments' => $comments, 'postRoute' => route('comments.plant', $plant->id)])
</div>
@endsection

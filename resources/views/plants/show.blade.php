@extends('layouts.app')
@section('title', $plant->name)

@section('content')
<nav class="mb-3"><a href="{{ route('plants.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Quay lại danh sách</a></nav>

<div class="row g-4">
    <div class="col-md-5">
        <img src="{{ $plant->image }}" class="img-fluid rounded-3 shadow-sm w-100" alt="{{ $plant->name }}"
             onerror="this.onerror=null;this.src='https://placehold.co/600x500/e8f5e9/2e7d32?text=GreenGarden'">
    </div>
    <div class="col-md-7">
        <small class="text-muted">{{ $plant->category->name }}</small>
        <h2 class="fw-bold">{{ $plant->name }}</h2>
        <div class="fs-3 fw-bold text-green my-2">@money($plant->price)</div>
        @if($plant->rating_count > 0)
            <div class="mb-2"><span class="text-warning">@for($i = 1; $i <= 5; $i++)<i class="fa{{ $i <= round($plant->rating_avg) ? 's' : 'r' }} fa-star"></i>@endfor</span>
                <strong>{{ $plant->rating_avg }}</strong>/5 · <a href="#reviews">{{ $plant->rating_count }} đánh giá</a></div>
        @endif

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
            <div class="col-6 col-md-3">@include('partials.plant-card', ['plant' => $item])</div>
        @endforeach
    </div>
@endif

{{-- ===== ĐÁNH GIÁ ===== --}}
<div class="row g-4 mt-4" id="reviews">
    <div class="col-lg-4">
        <div class="card card-body shadow-sm">
            <h5 class="fw-bold">Đánh giá từ người mua</h5>
            <div class="display-6 fw-bold">{{ $plant->rating_count ? $plant->rating_avg : '–' }}<small class="fs-6 text-muted">/5</small></div>
            <div class="small text-muted mb-2">{{ $plant->rating_count }} đánh giá đã duyệt</div>
            @for($star = 5; $star >= 1; $star--)
                @php $n = (int) ($distribution[$star] ?? 0); @endphp
                <div class="d-flex align-items-center gap-2 small">
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
                        <select name="rating" class="form-select form-select-sm mb-2" required>
                            @for($i = 5; $i >= 1; $i--)<option value="{{ $i }}" @selected((int) old('rating', $myReview->rating ?? 5) === $i)>{{ str_repeat('★', $i) }} ({{ $i }} sao)</option>@endfor
                        </select>
                        <input name="title" class="form-control form-control-sm mb-2" maxlength="150" placeholder="Tiêu đề" value="{{ old('title', $myReview->title ?? '') }}">
                        <textarea name="content" rows="3" class="form-control form-control-sm mb-2" maxlength="2000" placeholder="Cây có như mô tả? Đóng gói thế nào?">{{ old('content', $myReview->content ?? '') }}</textarea>
                        <button class="btn btn-sm btn-green">{{ $myReview ? 'Cập nhật đánh giá' : 'Gửi đánh giá' }}</button>
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
            <div class="card card-body shadow-sm mb-3">
                <div class="d-flex justify-content-between">
                    <div><strong>{{ $r->user->name }}</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Đã mua hàng</span></div>
                    <span class="small text-muted">@vndt($r->created_at)</span>
                </div>
                <div class="text-warning">{{ str_repeat('★', $r->rating) }}<span class="text-muted">{{ str_repeat('★', 5 - $r->rating) }}</span></div>
                @if($r->title)<div class="fw-bold">{{ $r->title }}</div>@endif
                @if($r->content)<div style="white-space: pre-line">{{ $r->content }}</div>@endif
                @if($r->shop_reply)
                    <div class="mt-2 p-2 bg-success bg-opacity-10 border-start border-success border-3 small">
                        <strong>Shop phản hồi:</strong> <span style="white-space: pre-line">{{ $r->shop_reply }}</span></div>
                @endif
                <div class="mt-2 small">
                    @auth
                        @if(auth()->id() !== $r->user_id)
                            <form action="{{ route('reviews.vote', $r) }}" method="POST" class="d-inline">@csrf
                                <button class="btn btn-sm {{ in_array($r->id, $votedIds) ? 'btn-success' : 'btn-outline-secondary' }}"><i class="fa fa-thumbs-up"></i> Hữu ích ({{ $r->helpful_count }})</button></form>
                        @else <span class="text-muted">{{ $r->helpful_count }} người thấy hữu ích</span> @endif
                    @else <span class="text-muted">{{ $r->helpful_count }} người thấy hữu ích</span> @endauth
                </div>
            </div>
        @empty
            <div class="alert alert-light">Chưa có đánh giá nào cho cây này.</div>
        @endforelse
        {{ $reviews->links() }}
    </div>
</div>

<div class="mt-4">
    @include('partials.comments', ['comments' => $comments, 'postRoute' => route('comments.plant', $plant->id)])
</div>
@endsection
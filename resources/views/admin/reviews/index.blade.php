@extends('layouts.admin')
@section('title', 'Đánh giá')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-star"></i> Đánh giá cây</h4>
<div class="mb-3 d-flex gap-1">
    @foreach(['pending' => 'Chờ duyệt', 'approved' => 'Đang hiển thị', 'rejected' => 'Từ chối'] as $v => $l)
        <a href="{{ route('admin.reviews.index', ['status' => $v]) }}" class="btn btn-sm {{ $status === $v ? 'btn-success' : 'btn-outline-success' }}">{{ $l }} ({{ $counts[$v] ?? 0 }})</a>
    @endforeach
</div>
<p class="small text-muted">Đánh giá của khách đã nhận hàng được đăng ngay. Đánh giá không phù hợp: bấm "Từ chối" (ghi lý do) để gỡ khỏi trang cây.
    Đánh giá đã bị từ chối mà khách sửa lại sẽ vào mục "Chờ duyệt".</p>
@forelse($reviews as $r)
    <div class="card card-body shadow-sm mb-3">
        <div class="d-flex justify-content-between">
            <div><strong>{{ $r->plant->name }}</strong> · <span class="text-warning">{{ str_repeat('★', $r->rating) }}</span> {{ $r->rating }}/5
                · {{ $r->user->name }} · mua trong đơn <a href="{{ route('admin.orders.show', $r->orderItem->order) }}">{{ $r->orderItem->order->code }}</a></div>
            <span class="small text-muted">@vndt($r->created_at) · {{ $r->helpful_count }} hữu ích</span>
        </div>
        @if($r->title)<div class="fw-bold mt-1">{{ $r->title }}</div>@endif
        <div style="white-space: pre-line">{{ $r->content }}</div>
        @if($r->reject_reason)<div class="text-danger small">Lý do từ chối: {{ $r->reject_reason }}</div>@endif

        <div class="d-flex flex-wrap gap-2 mt-2 align-items-start">
            @if($r->status->value !== 'approved')
                <form action="{{ route('admin.reviews.moderate', $r) }}" method="POST">@csrf<input type="hidden" name="status" value="approved">
                    <button class="btn btn-sm btn-success">Duyệt</button></form>
            @endif
            @if($r->status->value !== 'rejected')
                <form action="{{ route('admin.reviews.moderate', $r) }}" method="POST" class="d-flex gap-1">@csrf<input type="hidden" name="status" value="rejected">
                    <input name="reason" class="form-control form-control-sm" placeholder="Lý do từ chối" required>
                    <button class="btn btn-sm btn-outline-danger text-nowrap">Từ chối</button></form>
            @endif
            <form action="{{ route('admin.reviews.reply', $r) }}" method="POST" class="d-flex gap-1 flex-grow-1">@csrf
                <input name="shop_reply" value="{{ $r->shop_reply }}" class="form-control form-control-sm" placeholder="Shop trả lời công khai">
                <button class="btn btn-sm btn-outline-green text-nowrap">Lưu trả lời</button></form>
            <form action="{{ route('admin.reviews.destroy', $r) }}" method="POST" onsubmit="return confirm('Xóa đánh giá?')">@csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-secondary"><i class="fa fa-trash"></i></button></form>
        </div>
    </div>
@empty
    <div class="alert alert-light">Không có đánh giá nào.</div>
@endforelse
{{ $reviews->links() }}
@endsection

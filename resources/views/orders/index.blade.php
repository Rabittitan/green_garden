@extends('layouts.app')
@section('title', 'Đơn hàng của tôi')

@section('content')
@include('account._nav')
<div class="gg-page-head">
    <h1><i class="fa fa-clock-rotate-left text-green"></i> Đơn hàng của tôi</h1>
</div>

<ul class="nav nav-pills gap-2 mb-3">
    <li class="nav-item"><a class="nav-link {{ $tab === 'orders' ? 'active' : '' }}" href="{{ route('orders.index') }}">Tất cả đơn</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'review' ? 'active' : '' }}" href="{{ route('orders.index', ['tab' => 'cho-danh-gia']) }}">
        <i class="fa fa-star"></i> Chờ đánh giá @if($toReview->isNotEmpty())<span class="badge bg-danger ms-1">{{ $toReview->count() }}</span>@endif</a></li>
</ul>

@if($tab === 'review')
    {{-- Chấm sao ngay tại đây: gửi vào cùng luồng đánh giá ở trang cây (ReviewController::store), đánh giá đăng ngay --}}
    @forelse($toReview as $item)
        <div class="gg-panel gg-panel-body mb-3 gg-review-row">
            <div class="d-flex gap-3 align-items-start">
                <img src="{{ $item->plant->image }}" alt="{{ $item->plant_name }}" class="gg-thumb" loading="lazy">
                <div class="flex-grow-1">
                    <div class="fw-bold"><a href="{{ route('plants.show', $item->plant_id) }}" class="text-decoration-none">{{ $item->plant_name }}</a></div>
                    <div class="small text-muted mb-2">Trong đơn {{ $item->order->code }} · nhận ngày @vndt($item->order->completed_at)</div>
                    <form action="{{ route('reviews.store', $item->plant_id) }}" method="POST" class="row g-2">
                        @csrf
                        <div class="col-sm-4 col-lg-3">
                            <label class="visually-hidden" for="rating-{{ $item->id }}">Số sao</label>
                            <select id="rating-{{ $item->id }}" name="rating" class="form-select form-select-sm" required>
                                @for($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ str_repeat('★', $i) }} ({{ $i }} sao)</option>@endfor
                            </select>
                        </div>
                        <div class="col-sm-8 col-lg-4"><input name="title" maxlength="150" class="form-control form-control-sm" placeholder="Tiêu đề (không bắt buộc)"></div>
                        <div class="col-12 col-lg-5"><input name="content" maxlength="2000" class="form-control form-control-sm" placeholder="Cây có như mô tả? Đóng gói thế nào?"></div>
                        <div class="col-12"><button class="btn btn-sm btn-green"><i class="fa fa-paper-plane"></i> Gửi đánh giá</button></div>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center bg-white border rounded-4 py-5 px-3">
            <div class="gg-auth-icon mx-auto"><i class="fa fa-star"></i></div>
            <h2 class="h5 fw-bold">Không còn cây nào chờ đánh giá.</h2>
            <p class="text-muted mb-0">Cây trong các đơn đã giao thành công sẽ hiện ở đây để bạn chấm sao.</p>
        </div>
    @endforelse
@elseif($orders->isEmpty())
    <div class="text-center bg-white border rounded-4 py-5 px-3">
        <div class="gg-auth-icon mx-auto"><i class="fa fa-receipt"></i></div>
        <h2 class="h5 fw-bold">Bạn chưa có đơn hàng nào.</h2>
        <a href="{{ route('plants.index') }}" class="btn btn-green mt-2">Bắt đầu mua sắm</a>
    </div>
@else
    <div class="gg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr class="small text-muted"><th class="ps-3">Mã đơn</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th><th></th></tr></thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td class="fw-bold ps-3">{{ $order->code }}</td>
                            <td>@vndt($order->placed_at)</td>
                            <td class="fw-bold">@money($order->total)</td>
                            <td><span class="badge rounded-pill bg-{{ $order->status->color() }}">{{ $order->status->label() }}</span></td>
                            <td><span class="badge rounded-pill bg-{{ $order->payment_status->color() }}">{{ $order->payment_status->label() }}</span></td>
                            <td class="text-end pe-3"><a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-outline-green">Xem</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
@endif
@endsection

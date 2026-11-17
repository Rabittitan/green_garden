@extends('layouts.app')
@section('title', 'Giỏ hàng')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-shopping-cart"></i> Giỏ hàng của bạn</h4>

@if($lines->isEmpty())
    <div class="alert alert-info">Giỏ hàng đang trống. <a href="{{ route('plants.index') }}">Mua sắm ngay</a></div>
@else
    <div class="table-responsive">
        <table class="table align-middle bg-white shadow-sm">
            <thead class="table-success">
                <tr><th>Sản phẩm</th><th>Đơn giá</th><th class="text-center">Số lượng</th><th>Thành tiền</th><th></th></tr>
            </thead>
            <tbody>
                @foreach($lines as $id => $line)
                    <tr>
                        <td>
                            <img src="{{ $line['plant']->image }}" width="60" height="60" class="rounded me-2" style="object-fit:cover"
                                 onerror="this.onerror=null;this.src='https://placehold.co/60x60/e8f5e9/2e7d32?text=GL'">
                            <a href="{{ route('plants.show', $id) }}" class="text-decoration-none text-dark">{{ $line['plant']->name }}</a>
                            <div class="small text-muted">Còn {{ $line['plant']->stock }} cây</div>
                        </td>
                        <td>@money($line['plant']->price)</td>
                        <td class="text-center">
                            <form action="{{ route('cart.update', $id) }}" method="POST" class="d-inline-flex align-items-center gap-2">
                                @csrf
                                <button name="action" value="decrease" class="btn btn-sm btn-outline-secondary">-</button>
                                <strong>{{ $line['quantity'] }}</strong>
                                <button name="action" value="increase" class="btn btn-sm btn-outline-secondary" @disabled($line['quantity'] >= $line['plant']->stock)>+</button>
                            </form>
                        </td>
                        <td class="fw-bold">@money($line['total'])</td>
                        <td>
                            <form action="{{ route('cart.remove', $id) }}" method="POST">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <a href="{{ route('plants.index') }}" class="btn btn-outline-green">← Tiếp tục mua</a>
        <div class="text-end">
            <div class="fs-5">Tạm tính: <strong class="text-green">@money($subtotal)</strong></div>
            <div class="small text-muted">Ưu đãi hạng thành viên, mã giảm giá và phí ship được tính ở bước đặt hàng.</div>
            <a href="{{ route('checkout.form') }}" class="btn btn-green btn-lg mt-2">Tiến hành đặt hàng</a>
        </div>
    </div>
@endif
@endsection

@extends('layouts.app')
@section('title', 'Cổng thanh toán giả lập')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning fw-bold"><i class="fa fa-flask"></i> CỔNG THANH TOÁN GIẢ LẬP (chỉ dùng khi phát triển)</div>
            <div class="card-body">
                <p>Đơn hàng <strong>{{ $payment->order->code }}</strong> · Mã giao dịch <code>{{ $payment->txn_ref }}</code></p>
                <p class="fs-4">Số tiền: <strong class="text-danger">@money($payment->amount)</strong></p>
                <p class="small text-muted">Bấm một nút: cổng sẽ gọi IPN (có chữ ký) về shop như VNPay thật, rồi đưa bạn về trang kết quả.</p>
                <div class="d-flex gap-2">
                    @foreach(['success' => ['btn-success', 'Thanh toán thành công'], 'fail' => ['btn-outline-danger', 'Hủy / thất bại']] as $outcome => [$cls, $label])
                        <form action="{{ route('payment.fake.submit') }}" method="POST">
                            @csrf
                            <input type="hidden" name="vnp_TxnRef" value="{{ $payment->txn_ref }}">
                            <input type="hidden" name="return_url" value="{{ $query['vnp_ReturnUrl'] ?? route('payment.vnpay.return') }}">
                            <input type="hidden" name="outcome" value="{{ $outcome }}">
                            <button class="btn {{ $cls }}">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

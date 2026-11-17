<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;

/**
 * Cổng thanh toán.
 *  - IPN (server -> server): NƠI DUY NHẤT ghi nhận tiền. Public, không CSRF (GET), không đăng nhập.
 *  - Return URL (trình duyệt quay về): chỉ kiểm chữ ký và HIỂN THỊ, không ghi DB.
 *  - Trang giả lập: chỉ bật khi PAYMENT_GATEWAY=fake (dev).
 */
class PaymentController extends Controller
{
    private const MESSAGES = [
        '00' => 'Confirm Success', '01' => 'Order not found', '02' => 'Order already confirmed',
        '04' => 'Invalid amount', '97' => 'Invalid signature', '99' => 'Unknown error',
    ];

    public function vnpayIpn(Request $request, PaymentService $payments)
    {
        try {
            $code = $payments->handleCallback($request->query(), $request->ip());
        } catch (\Throwable $e) {
            report($e);
            $code = '99';                                               // VNPay sẽ gọi lại
        }

        return response()->json(['RspCode' => $code, 'Message' => self::MESSAGES[$code] ?? 'Unknown error']);
    }

    public function vnpayReturn(Request $request, PaymentGateway $gateway)
    {
        $result = $gateway->parseCallback($request->query());
        $payment = $result->signatureValid && $result->txnRef ? Payment::where('txn_ref', $result->txnRef)->first() : null;
        if (! $payment) {
            return redirect()->route('orders.index')->with('error', 'Không xác minh được kết quả thanh toán.');
        }
        $order = $payment->order;
        abort_unless($request->user()?->id === $order->user_id, 404);

        // Trạng thái hiển thị lấy từ DB (do IPN ghi), KHÔNG từ tham số trên URL
        [$type, $msg] = match (true) {
            $order->status !== OrderStatus::PendingPayment && $order->paid_amount > 0 => ['success', 'Thanh toán thành công! Đơn hàng đang chờ shop xác nhận.'],
            ! $result->success => ['error', 'Thanh toán chưa thành công. Bạn có thể thử thanh toán lại trước khi đơn hết hạn.'],
            default => ['warning', 'Đang chờ cổng thanh toán xác nhận. Vui lòng tải lại trang sau ít phút.'],
        };

        return redirect()->route('orders.show', $order)->with($type, $msg);
    }

    /** Trang thanh toán giả lập (dev). */
    public function fakeShow(Request $request)
    {
        abort_unless(PaymentMethod::onlineGatewayMode() === 'fake', 404);   // không bao giờ bật ở production
        $payment = Payment::where('txn_ref', $request->query('vnp_TxnRef'))->firstOrFail();

        return view('payment.fake', ['payment' => $payment->load('order'), 'query' => $request->query()]);
    }

    /** Giả lập: cổng gọi IPN (server -> server) rồi đưa trình duyệt về return URL, giống hệt VNPay. */
    public function fakeSubmit(Request $request, PaymentGateway $gateway, PaymentService $payments)
    {
        abort_unless(PaymentMethod::onlineGatewayMode() === 'fake', 404);   // không bao giờ bật ở production
        $data = $request->validate(['vnp_TxnRef' => ['required', 'string'], 'return_url' => ['required', 'url'], 'outcome' => ['required', 'in:success,fail']]);
        $payment = Payment::where('txn_ref', $data['vnp_TxnRef'])->firstOrFail();
        $ok = $data['outcome'] === 'success';

        parse_str($gateway->sign([
            'vnp_TmnCode' => 'FAKE', 'vnp_TxnRef' => $payment->txn_ref, 'vnp_Amount' => (string) ($payment->amount * 100),
            'vnp_ResponseCode' => $ok ? '00' : '24', 'vnp_TransactionStatus' => $ok ? '00' : '02',
            'vnp_TransactionNo' => (string) random_int(10_000_000, 99_999_999), 'vnp_BankCode' => 'FAKE',
            'vnp_PayDate' => now('Asia/Ho_Chi_Minh')->format('YmdHis'),
        ]), $signed);
        $payments->handleCallback($signed, $request->ip());

        return redirect()->away($data['return_url'].'?'.http_build_query($signed));
    }
}

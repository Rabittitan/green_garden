<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * VNPay 2.1.0: ký HMAC-SHA512 trên chuỗi query đã sắp xếp theo khóa.
 * - Số tiền gửi sang = VND × 100.
 * - Thành công khi vnp_ResponseCode = 00 VÀ vnp_TransactionStatus = 00.
 * - CHỈ IPN (server -> server) được ghi nhận thanh toán; return URL chỉ để hiển thị.
 */
class VnpayGateway implements PaymentGateway
{
    public function __construct(
        protected string $tmnCode,
        protected string $hashSecret,
        protected string $baseUrl,
        protected string $version = '2.1.0',
    ) {
        if ($this->hashSecret === '') {
            throw new LogicException('Thiếu VNPAY_HASH_SECRET.');
        }
    }

    public function name(): string
    {
        return 'vnpay';
    }

    public function initParams(Payment $payment, string $clientIp): array
    {
        $tz = 'Asia/Ho_Chi_Minh';                                  // VNPay yêu cầu giờ GMT+7
        $expires = $payment->order->payment_expires_at ?? now()->addMinutes(15);

        return [
            'create_date' => now($tz)->format('YmdHis'),
            'expire_date' => Carbon::parse($expires)->setTimezone($tz)->format('YmdHis'),
            'ip' => $clientIp,
        ];
    }

    public function paymentUrl(Payment $payment, string $returnUrl): string
    {
        $init = $payment->raw_payload['init'] ?? throw new LogicException('Payment chưa có thông số khởi tạo.');
        $params = [
            'vnp_Version' => $this->version,
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->tmnCode,
            'vnp_Amount' => (string) ($payment->amount * 100),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $payment->txn_ref,
            'vnp_OrderInfo' => 'Thanh toan don hang '.$payment->order->code,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => $init['ip'],
            'vnp_CreateDate' => $init['create_date'],
            'vnp_ExpireDate' => $init['expire_date'],
        ];

        return $this->baseUrl.'?'.$this->sign($params);
    }

    public function parseCallback(array $query): GatewayResult
    {
        $params = array_filter($query, fn ($k) => str_starts_with((string) $k, 'vnp_'), ARRAY_FILTER_USE_KEY);
        $given = strtolower((string) ($params['vnp_SecureHash'] ?? ''));
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);

        $valid = $given !== '' && hash_equals($this->hash($params), $given);
        $amount = isset($params['vnp_Amount']) && ctype_digit((string) $params['vnp_Amount'])
            ? intdiv((int) $params['vnp_Amount'], 100) : null;

        return new GatewayResult(
            signatureValid: $valid,
            txnRef: $params['vnp_TxnRef'] ?? null,
            success: $valid && ($params['vnp_ResponseCode'] ?? null) === '00' && ($params['vnp_TransactionStatus'] ?? null) === '00',
            amount: $amount,
            providerTxnNo: $params['vnp_TransactionNo'] ?? null,
            responseCode: $params['vnp_ResponseCode'] ?? null,
            raw: $query,
        );
    }

    /** Trả về query string đã kèm vnp_SecureHash (dùng cả cho test / cổng giả lập). */
    public function sign(array $params): string
    {
        return $this->query($params).'&vnp_SecureHash='.$this->hash($params);
    }

    protected function hash(array $params): string
    {
        return hash_hmac('sha512', $this->query($params), $this->hashSecret);
    }

    protected function query(array $params): string
    {
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $parts[] = urlencode((string) $k).'='.urlencode((string) $v);
        }

        return implode('&', $parts);
    }
}

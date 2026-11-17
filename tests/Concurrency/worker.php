<?php

/*
 * Tiến trình con cho ConcurrencyTest: boot app, chờ tới mốc thời gian chung rồi thực hiện 1 thao tác,
 * in kết quả JSON. Chạy: php tests/Concurrency/worker.php <startAt> <command> [args...]
 */

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Orders\PlaceOrderData;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $startAt, $command] = $argv;
$args = array_slice($argv, 3);
DB::connection()->getPdo();          // kết nối sẵn trước vạch xuất phát
while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $result = match ($command) {
        'place' => app(OrderService::class)->place(User::findOrFail((int) $args[0]), new PlaceOrderData(
            cart: json_decode($args[1], true), paymentMethod: PaymentMethod::from($args[3] ?? 'cod'), checkoutToken: $args[2],
            shipping: ['recipient_name' => 'K', 'phone' => '0900000000', 'province' => 'HCM', 'district' => 'Q1', 'ward' => 'BN', 'address_line' => '1'],
        ))->id,
        'cancel' => app(OrderService::class)->cancel(Order::findOrFail((int) $args[0]), ActorType::Staff,
            User::where('role', 'admin')->firstOrFail(), 'Hủy song song')->id,
        'expire' => app(OrderService::class)->expireOverdue(),
        'ipn' => (function () use ($args) {
            $p = Payment::where('txn_ref', $args[0])->firstOrFail();
            parse_str(app(PaymentGateway::class)->sign([
                'vnp_TmnCode' => 'FAKE', 'vnp_TxnRef' => $p->txn_ref, 'vnp_Amount' => (string) ($p->amount * 100),
                'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_TransactionNo' => (string) random_int(1e7, 9e7),
            ]), $q);

            return app(PaymentService::class)->handleCallback($q);
        })(),
    };
    echo json_encode(['ok' => true, 'result' => $result]);
} catch (BusinessException $e) {
    echo json_encode(['ok' => false, 'business' => true, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'business' => false, 'error' => get_class($e).': '.$e->getMessage()]);
}

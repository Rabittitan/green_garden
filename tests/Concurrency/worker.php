<?php

/*
 * Tiến trình con cho ConcurrencyTest: boot app, chờ tới mốc thời gian chung rồi thực hiện 1 thao tác,
 * in kết quả JSON. Chạy: php tests/Concurrency/worker.php <startAt> <command> [args...]
 */

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\DefectReport;
use App\Models\FlashSale;
use App\Models\GoodsReceipt;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StockCount;
use App\Models\User;
use App\Services\CouponGiftService;
use App\Services\CouponService;
use App\Services\DefectReportService;
use App\Services\FlashSaleService;
use App\Services\Orders\PlaceOrderData;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\ReviewService;
use App\Services\StockCountService;
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
            couponCode: $args[4] ?? null,
        ))->id,
        // Nhóm B: admin sửa phạm vi mã (thêm cây) đúng lúc khách đặt hàng bằng mã đó
        'coupon-update' => app(CouponService::class)->update(Coupon::findOrFail((int) $args[0]),
            ['name' => 'Sửa song song '.getmypid(), 'value' => (int) $args[2]], json_decode($args[1], true), [])->id,
        'cancel' => app(OrderService::class)->cancel(Order::findOrFail((int) $args[0]), ActorType::Staff,
            User::where('role', 'admin')->firstOrFail(), 'Hủy song song')->id,
        'expire' => app(OrderService::class)->expireOverdue(),
        'confirm' => app(OrderService::class)->confirm(Order::findOrFail((int) $args[0]), User::where('role', 'admin')->firstOrFail())->status->value,
        'ship' => app(OrderService::class)->ship(Order::findOrFail((int) $args[0]), 'GHN', 'RACE', User::where('role', 'admin')->firstOrFail())->status->value,
        // Flash sale: 2 quản trị viên cùng lúc duyệt 2 đợt trùng giờ có chung cây
        'flash-approve' => app(FlashSaleService::class)->approve(FlashSale::findOrFail((int) $args[0]), User::findOrFail((int) $args[1]))->status->value,
        // Phiếu báo lỗi: quản trị viên duyệt xuất hủy đúng lúc nhân viên quét chậu đó để giao
        'defect-approve' => app(DefectReportService::class)->approve(DefectReport::findOrFail((int) $args[0]), User::where('role', 'admin')->firstOrFail())->status,
        // Mã từng chậu: 2 nhân viên quét CÙNG chậu cho 2 đơn khác nhau
        'ship-codes' => app(OrderService::class)->ship(Order::findOrFail((int) $args[0]), 'GHN', 'RACE', User::where('role', 'admin')->firstOrFail(),
            json_decode($args[1], true))->status->value,
        'confirm-refund' => app(PaymentService::class)->confirmRefund(Payment::findOrFail((int) $args[0]), User::where('role', 'admin')->firstOrFail(), 'RF-'.getmypid())->status->value,
        'complete-receipt' => (function () use ($args) {
            app(PurchaseService::class)->completeReceipt(GoodsReceipt::findOrFail((int) $args[0]), null);

            return 'ok';
        })(),
        'complete-count' => app(StockCountService::class)->complete(StockCount::findOrFail((int) $args[0]), User::where('role', 'admin')->firstOrFail())->status,
        'issue-gifts' => app(CouponGiftService::class)->issueDueGifts(),
        'review' => app(ReviewService::class)->submit(User::findOrFail((int) $args[0]), (int) $args[1], 5, null, 'Song song')->id,
        'draft-pr' => app(PurchaseService::class)->draftFromLowStock(User::findOrFail((int) $args[0]))?->code,
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

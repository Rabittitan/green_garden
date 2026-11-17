<?php

use App\Models\PaymentWebhookLog;
use App\Services\MembershipService;
use App\Services\OrderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tác vụ nền. Production cần cron:  * * * * * php /đường-dẫn/artisan schedule:run
 * Dev: chạy  php artisan schedule:work
 */

Artisan::command('orders:expire-unpaid', function (OrderService $orders) {
    $this->info('Đã hủy '.$orders->expireOverdue().' đơn quá hạn thanh toán.');
})->purpose('Hủy đơn chờ thanh toán đã quá hạn, trả hàng về kho');

Artisan::command('membership:record-spend', function (MembershipService $membership) {
    $this->info('Đã chốt chi tiêu cho '.$membership->recordDueSpend(1000).' đơn.');
})->purpose('Cộng các đơn đã qua hạn đổi trả vào tổng chi tiêu, tự lên hạng');

Artisan::command('membership:recalculate-all', function (MembershipService $membership) {
    $this->info('Đã nâng hạng '.$membership->recalculateAll().' khách.');
})->purpose('Tính lại tổng chi tiêu + hạng cho mọi khách (sau khi đổi ngưỡng hạng)');

Artisan::command('payments:prune-webhook-logs {--days=180}', function () {
    $n = PaymentWebhookLog::where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();
    $this->info("Đã xóa {$n} log callback thanh toán cũ.");
})->purpose('Dọn log callback thanh toán cũ');

Artisan::command('shop:reconcile', function () {
    $checks = [
        'Tồn kho lệch sổ kho' => 'SELECT p.id FROM plants p WHERE p.stock <> (SELECT COALESCE(SUM(s.quantity),0) FROM stock_movements s WHERE s.plant_id = p.id)',
        'Giảm giá dòng lệch đơn' => 'SELECT o.id FROM orders o JOIN order_items i ON i.order_id = o.id GROUP BY o.id, o.member_discount, o.coupon_discount, o.subtotal
                                      HAVING SUM(i.member_discount) <> o.member_discount OR SUM(i.coupon_discount) <> o.coupon_discount OR SUM(i.line_subtotal) <> o.subtotal',
        'Tiền đã thu / đã hoàn lệch payments' => "SELECT o.id FROM orders o WHERE o.paid_amount <> (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.order_id = o.id AND p.type = 'charge' AND p.status = 'succeeded')
                                      OR o.refunded_amount <> (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.order_id = o.id AND p.type = 'refund' AND p.status = 'succeeded')",
        'Lượt dùng mã lệch' => "SELECT c.id FROM coupons c WHERE c.used_count <> (SELECT COUNT(*) FROM coupon_redemptions r WHERE r.coupon_id = c.id AND r.status = 'applied')",
        'Đơn chờ thanh toán quá hạn > 10 phút' => "SELECT id FROM orders WHERE status = 'pending_payment' AND payment_expires_at < NOW() - INTERVAL 10 MINUTE",
        'Tổng chi tiêu khách lệch' => 'SELECT u.id FROM users u WHERE u.lifetime_spend <> (SELECT COALESCE(SUM(GREATEST(0, CAST(o.paid_amount AS SIGNED) - CAST(o.refunded_amount AS SIGNED)
                                      - (CAST(o.shipping_fee AS SIGNED) - CAST(o.shipping_discount AS SIGNED)))),0) FROM orders o WHERE o.user_id = u.id AND o.spend_recorded_at IS NOT NULL)',
    ];
    $failed = 0;
    foreach ($checks as $label => $sql) {
        $ids = array_column(DB::select($sql), 'id');
        if ($ids) {
            $failed++;
            $this->error("✗ {$label}: ".implode(', ', array_slice($ids, 0, 20)));
            Log::error("Đối soát thất bại: {$label}", ['ids' => $ids]);
        } else {
            $this->info("✓ {$label}");
        }
    }

    return $failed === 0 ? 0 : 1;
})->purpose('Đối soát bất biến dữ liệu (kho, tiền, mã, hạng)');

Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('membership:record-spend')->hourly()->withoutOverlapping();
Schedule::command('payments:prune-webhook-logs')->dailyAt('03:23')->timezone('Asia/Ho_Chi_Minh');
Schedule::command('shop:reconcile')->dailyAt('02:17')->timezone('Asia/Ho_Chi_Minh');

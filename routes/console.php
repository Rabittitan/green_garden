<?php

use App\Enums\UserRole;
use App\Models\PaymentWebhookLog;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\OrderService;
use App\Services\ReviewService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tác vụ nền. Production cần cron:  * * * * * php /đường-dẫn/artisan schedule:run
 * và 1 tiến trình queue worker (mail): xem deploy/ và docs/H7-van-hanh.md
 * Dev: chạy  php artisan schedule:work  +  php artisan queue:work
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

Artisan::command('shop:create-admin {email} {--name=Quản trị viên} {--staff : Tạo nhân viên thay vì admin}', function () {
    $email = mb_strtolower(trim((string) $this->argument('email')));
    $role = $this->option('staff') ? UserRole::Staff : UserRole::Admin;
    $password = (string) $this->secret('Mật khẩu (ít nhất 10 ký tự, có chữ và số)');
    $v = Validator::make(
        ['email' => $email, 'password' => $password, 'password_confirmation' => (string) $this->secret('Nhập lại mật khẩu')],
        ['email' => 'required|email', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]],
    );
    if ($v->fails()) {
        foreach ($v->errors()->all() as $msg) {
            $this->error($msg);
        }

        return 1;
    }

    $user = User::where('email', $email)->first();
    if ($user && ! $this->confirm("Email {$email} đã có tài khoản ({$user->role->value}). Đổi thành {$role->value} và đặt lại mật khẩu?")) {
        return 1;
    }
    // Tạo mới qua User::create để model tự gán hạng cơ bản + ghi lịch sử hạng như khách đăng ký
    $user ??= User::create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => Hash::make($password)]);
    $user->forceFill(['role' => $role, 'is_active' => true, 'password' => Hash::make($password),
        'email_verified_at' => $user->email_verified_at ?? now()])->save();
    $this->info("Đã tạo/cập nhật {$role->value}: {$email}");

    return 0;
})->purpose('Tạo tài khoản quản trị (production không chạy seeder mẫu)');

Artisan::command('reviews:rebuild', function (ReviewService $reviews) {
    $reviews->rebuild();
    $this->info('Đã dựng lại điểm sao và lượt hữu ích từ dữ liệu gốc.');
})->purpose('Sửa lệch điểm đánh giá khi shop:reconcile báo lỗi');

Artisan::command('shop:reconcile', function () {
    $checks = [
        'Tồn kho lệch sổ kho' => 'SELECT p.id FROM plants p WHERE p.stock <> (SELECT COALESCE(SUM(s.quantity),0) FROM stock_movements s WHERE s.plant_id = p.id)',
        'Giảm giá dòng lệch đơn' => 'SELECT o.id FROM orders o JOIN order_items i ON i.order_id = o.id GROUP BY o.id, o.member_discount, o.coupon_discount, o.subtotal
                                      HAVING SUM(i.member_discount) <> o.member_discount OR SUM(i.coupon_discount) <> o.coupon_discount OR SUM(i.line_subtotal) <> o.subtotal',
        'Tiền đã thu / đã hoàn lệch payments' => "SELECT o.id FROM orders o WHERE o.paid_amount <> (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.order_id = o.id AND p.type = 'charge' AND p.status = 'succeeded')
                                      OR o.refunded_amount <> (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.order_id = o.id AND p.type = 'refund' AND p.status = 'succeeded')",
        'Lượt dùng mã lệch' => "SELECT c.id FROM coupons c WHERE c.used_count <> (SELECT COUNT(*) FROM coupon_redemptions r WHERE r.coupon_id = c.id AND r.status = 'applied')",
        'Điểm đánh giá lệch' => "SELECT p.id FROM plants p LEFT JOIN (SELECT plant_id, SUM(rating) s, COUNT(*) c FROM plant_reviews
                                      WHERE status = 'approved' AND deleted_at IS NULL GROUP BY plant_id) r ON r.plant_id = p.id
                                      WHERE p.rating_sum <> COALESCE(r.s, 0) OR p.rating_count <> COALESCE(r.c, 0)",
        'Lượt "hữu ích" lệch' => 'SELECT r.id FROM plant_reviews r WHERE r.helpful_count <> (SELECT COUNT(*) FROM review_votes v WHERE v.plant_review_id = r.id)',
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
// Job lỗi (mail gửi hỏng 3 lần) giữ 30 ngày để còn queue:retry, sau đó dọn
Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:41')->timezone('Asia/Ho_Chi_Minh');

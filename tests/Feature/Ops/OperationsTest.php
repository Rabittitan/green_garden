<?php

namespace Tests\Feature\Ops;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Plant;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\OrderService;
use App\Services\ReviewService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** H7 — vận hành: múi giờ DB, proxy, health check, lịch chạy nền, tạo admin production, chặn seed mẫu. */
class OperationsTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_mysql_session_runs_in_utc_like_the_app(): void
    {
        $this->assertSame('+00:00', DB::selectOne('SELECT @@session.time_zone AS tz')->tz);
        // Cột DEFAULT CURRENT_TIMESTAMP (placed_at, created_at của log...) và giờ Laravel ghi phải cùng hệ
        $dbNow = Carbon::parse(DB::selectOne('SELECT NOW() AS n')->n, 'UTC');
        $this->assertLessThan(5, abs($dbNow->diffInSeconds(now())), 'Giờ MySQL lệch giờ ứng dụng');
    }

    public function test_trusted_proxy_gives_real_client_ip_and_https(): void
    {
        $headers = ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'];

        // Không khai báo proxy: không tin header giả mạo
        $this->get('/', $headers)->assertHeaderMissing('Strict-Transport-Security');

        config(['shop.trusted_proxies' => '127.0.0.1']);
        $this->refreshApplicationWithConfig();
        $this->get('/', $headers)->assertHeader('Strict-Transport-Security');
        $this->assertSame('203.0.113.9', request()->ip());
    }

    public function test_login_limit_is_per_real_client_behind_proxy(): void
    {
        config(['shop.trusted_proxies' => '127.0.0.1']);
        $this->refreshApplicationWithConfig();
        // 20 lần/phút/IP: một khách gõ sai nhiều lần không được khóa đăng nhập của cả site
        for ($i = 0; $i < 20; $i++) {
            $this->post(route('login.post'), ['email' => "x{$i}@greenlife.test", 'password' => 'sai'], ['X-Forwarded-For' => '198.51.100.1']);
        }
        $this->post(route('login.post'), ['email' => 'y@greenlife.test', 'password' => 'sai'], ['X-Forwarded-For' => '198.51.100.1'])->assertStatus(429);
        $this->post(route('login.post'), ['email' => 'khach@greenlife.test', 'password' => '123456'], ['X-Forwarded-For' => '198.51.100.2'])->assertRedirect();
    }

    public function test_health_check_reports_database(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_background_jobs_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");
        foreach (['orders:expire-unpaid', 'membership:record-spend', 'shop:reconcile', 'payments:prune-webhook-logs', 'queue:prune-failed'] as $cmd) {
            $this->assertStringContainsString($cmd, $commands, "Thiếu lịch chạy {$cmd}");
        }
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        try {
            // (artisan db:seed ở production còn hỏi xác nhận; --force bỏ qua câu hỏi đó -> lớp chặn này mới là chốt cuối)
            $this->expectException(RuntimeException::class);
            $this->app->make(DatabaseSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('shop:create-admin', ['email' => 'Chu.Shop@GreenLife.vn', '--name' => 'Chủ shop'])
            ->expectsQuestion('Mật khẩu (ít nhất 10 ký tự, có chữ và số)', 'MatKhauManh2026')
            ->expectsQuestion('Nhập lại mật khẩu', 'MatKhauManh2026')
            ->assertSuccessful();

        $admin = User::where('email', 'chu.shop@greenlife.vn')->sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('MatKhauManh2026', $admin->password));
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotNull($admin->membership_tier_id);
        $this->assertSame(1, $admin->tierHistories()->count(), 'Phải có lịch sử hạng ban đầu như khách đăng ký');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_create_admin_rejects_weak_password(): void
    {
        $this->artisan('shop:create-admin', ['email' => 'yeu@greenlife.vn'])
            ->expectsQuestion('Mật khẩu (ít nhất 10 ký tự, có chữ và số)', '123456')
            ->expectsQuestion('Nhập lại mật khẩu', '123456')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'yeu@greenlife.vn']);
    }

    public function test_create_staff_promotes_existing_account_after_confirmation(): void
    {
        $this->artisan('shop:create-admin', ['email' => 'khach@greenlife.test', '--staff' => true])
            ->expectsQuestion('Mật khẩu (ít nhất 10 ký tự, có chữ và số)', 'NhanVien2026x')
            ->expectsQuestion('Nhập lại mật khẩu', 'NhanVien2026x')
            ->expectsConfirmation('Email khach@greenlife.test đã có tài khoản (customer). Đổi thành staff và đặt lại mật khẩu?', 'yes')
            ->assertSuccessful();
        $this->assertSame(UserRole::Staff, User::where('email', 'khach@greenlife.test')->sole()->role);
    }

    public function test_reviews_rebuild_fixes_what_reconcile_reports(): void
    {
        $orders = app(OrderService::class);
        $o = $this->place();
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'R1', $this->admin());
        $orders->complete($o, $this->admin());
        $reviews = app(ReviewService::class);
        $review = $reviews->submit($this->customer(), 1, 5, 'Cây đẹp', null);
        $reviews->moderate($review, ReviewStatus::Approved, $this->admin());
        $this->artisan('shop:reconcile')->assertSuccessful();

        // Ai đó sửa tay DB: lệch cả điểm sao lẫn lượt hữu ích
        DB::table('plants')->where('id', 1)->update(['rating_sum' => 99, 'rating_count' => 7]);
        DB::table('plant_reviews')->where('id', $review->id)->update(['helpful_count' => 12]);
        $this->artisan('shop:reconcile')->assertFailed();
        $this->artisan('reviews:rebuild')->assertSuccessful();
        $this->artisan('shop:reconcile')->assertSuccessful();
        $this->assertSame(0, $review->refresh()->helpful_count);
        $this->assertSame([5, 1], [Plant::find(1)->rating_sum, Plant::find(1)->rating_count]);
    }

    /** TrustProxies đọc config lúc boot provider -> boot lại sau khi đổi config (không dựng app mới: giữ transaction test). */
    private function refreshApplicationWithConfig(): void
    {
        $this->app->getProvider(AppServiceProvider::class)->boot();
    }
}

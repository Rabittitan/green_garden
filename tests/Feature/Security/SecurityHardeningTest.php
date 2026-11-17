<?php

namespace Tests\Feature\Security;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Comment;
use App\Models\Plant;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** H6 — bảo mật: header, lỗi không lộ chi tiết, XSS, giới hạn đăng nhập, open redirect, cấu hình. */
class SecurityHardeningTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_security_headers_on_every_page(): void
    {
        $r = $this->get(route('home'))->assertOk();
        $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('form-action \'self\' https://sandbox.vnpayment.vn', $csp, 'Thiếu host VNPay: chuyển hướng thanh toán sẽ bị chặn');
        $r->assertHeaderMissing('Strict-Transport-Security');                       // HTTP thường: không gửi HSTS

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_errors_never_leak_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/__boom', fn () => throw new RuntimeException('DB_PASSWORD=sieu-bi-mat'));

        $this->get('/__boom')->assertStatus(500)->assertSee('Có lỗi xảy ra')
            ->assertDontSee('sieu-bi-mat')->assertDontSee('RuntimeException')->assertDontSee('vendor/laravel');
        $this->get('/khong-co-trang-nay')->assertNotFound()->assertSee('Không tìm thấy trang');
        $this->actingAs($this->customer())->get(route('admin.dashboard'))->assertForbidden()->assertSee('Không có quyền truy cập');
    }

    public function test_user_content_is_escaped(): void
    {
        app(CommentService::class)->post($this->customer(), Plant::find(1), '<script>alert("xss")</script><img src=x onerror=alert(1)>');
        $html = $this->get(route('plants.show', 1))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertSame(1, Comment::count());
    }

    public function test_login_is_rate_limited_per_account_and_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), ['email' => 'khach@greenlife.test', 'password' => 'sai'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.post'), ['email' => 'khach@greenlife.test', 'password' => '123456'])->assertStatus(429);   // khóa tạm tài khoản này
        $this->post(route('login.post'), ['email' => 'admin@greenlife.test', 'password' => '123456'])->assertRedirect();     // tài khoản khác vẫn được
    }

    public function test_fake_gateway_cannot_redirect_to_another_site(): void
    {
        $order = $this->place(method: PaymentMethod::Vnpay);
        $this->post(route('payment.fake.submit'), ['vnp_TxnRef' => $order->payments()->sole()->txn_ref,
            'return_url' => 'https://lua-dao.example/nhan-tien', 'outcome' => 'success'])->assertStatus(422);
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_session_cookie_is_https_only_in_production_by_default(): void
    {
        // Env đọc $_SERVER/$_ENV trước getenv() -> phải đổi cả ba rồi trả lại
        $backup = [$_SERVER, $_ENV, getenv('APP_ENV')];
        foreach (['_SERVER', '_ENV'] as $g) {
            $GLOBALS[$g]['APP_ENV'] = 'production';
            unset($GLOBALS[$g]['SESSION_SECURE_COOKIE']);
        }
        putenv('APP_ENV=production');
        try {
            $this->assertTrue((require config_path('session.php'))['secure']);
        } finally {
            [$_SERVER, $_ENV] = $backup;
            putenv('APP_ENV='.$backup[2]);
        }
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_env_example_is_ready_for_mysql_and_safe_defaults(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString("\nDB_CONNECTION=mysql\n", $env);
        foreach (['PAYMENT_GATEWAY=', 'VNPAY_HASH_SECRET=', 'SHOP_BANK_ACCOUNT_NO=', 'SHOP_RETURN_ADDRESS='] as $key) {
            $this->assertStringContainsString($key, $env);
        }
        $this->assertDoesNotMatchRegularExpression('/^VNPAY_HASH_SECRET=\S+/m', $env, '.env.example không được chứa khóa thật');
    }
}

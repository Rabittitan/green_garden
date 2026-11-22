<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use App\Notifications\OrderShipped;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** K1: giao diện, thông báo lỗi và mail đều tiếng Việt; không còn câu tiếng Anh mặc định của Laravel. */
class VietnameseLocalizationTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private const ENGLISH_LEAKS = ['The ', ' field', 'must be', 'Regards', 'All rights reserved', 'having trouble', 'Hello!', 'Whoops', 'Laravel'];

    public function test_locale_and_shop_name(): void
    {
        $this->assertSame('vi', app()->getLocale());
        $this->assertSame('GreenLife Shop', config('app.name'));
        // Máy mới cài từ .env.example phải đúng ngay; .env có sẵn của máy cũ ghi đè giá trị này (xem docs/H7)
        $env = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString("\nAPP_NAME=\"GreenLife Shop\"\n", "\n".$env);
        $this->assertStringContainsString("\nAPP_LOCALE=vi\n", $env);
    }

    /** Mọi key validate / auth / passwords / pagination của bản Laravel đang cài phải có bản tiếng Việt (nâng cấp Laravel thêm key -> test đỏ). */
    public function test_every_framework_translation_key_has_vietnamese(): void
    {
        $en = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($a as $k => $v) {
                $key = $prefix.$k;
                is_array($v) ? $out = array_merge($out, $flatten($v, $key.'.')) : $out[] = $key;
            }

            return $out;
        };
        foreach (['validation', 'auth', 'passwords', 'pagination'] as $file) {
            $vi = $flatten(require lang_path("vi/{$file}.php"));
            $missing = array_diff($flatten(require "{$en}/{$file}.php"), $vi);
            $missing = array_filter($missing, fn ($k) => ! str_starts_with($k, 'custom.'));     // ví dụ mẫu của Laravel, không phải thông báo
            $this->assertSame([], array_values($missing), "lang/vi/{$file}.php thiếu key");
        }
    }

    public function test_register_form_errors_are_vietnamese(): void
    {
        $this->post(route('register.post'), ['name' => '', 'email' => 'khong-phai-email', 'password' => 'abc', 'password_confirmation' => 'xyz']);
        $errors = session('errors')->getBag('default')->all();

        $this->assertContains('Vui lòng nhập họ tên.', $errors);
        $this->assertContains('Email phải là địa chỉ email hợp lệ.', $errors);
        $this->assertContains('Mật khẩu nhập lại không khớp.', $errors);
        $this->assertContains('Mật khẩu phải dài ít nhất 8 ký tự.', $errors);
        $this->assertContains('Mật khẩu phải có ít nhất một chữ số.', $errors);
        foreach ($errors as $msg) {
            foreach (self::ENGLISH_LEAKS as $leak) {
                $this->assertStringNotContainsString($leak, $msg);
            }
        }
    }

    public function test_emails_have_no_english_boilerplate(): void
    {
        $user = $this->customer();
        $order = $this->place();
        $order->forceFill(['shipping_carrier' => 'GHN', 'tracking_code' => 'X1'])->save();

        foreach ([(new VerifyEmail)->toMail($user), (new OrderShipped($order))->toMail($user)] as $mail) {
            $html = (string) $mail->render();
            $this->assertStringContainsString('GreenLife Shop', $html);
            $this->assertStringContainsString('Bảo lưu mọi quyền', $html);
            $this->assertStringContainsString('Nếu không bấm được nút', $html);
            foreach (self::ENGLISH_LEAKS as $leak) {
                $this->assertStringNotContainsString($leak, strip_tags($html), "Mail còn câu tiếng Anh: {$leak}");
            }
        }
    }

    public function test_pagination_is_vietnamese(): void
    {
        User::factory()->count(25)->create();
        $html = $this->actingAs($this->admin())->get(route('admin.customers.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Hiển thị', $html);
        $this->assertStringContainsString('kết quả', $html);
        $this->assertStringNotContainsString('Showing', $html);
    }
}

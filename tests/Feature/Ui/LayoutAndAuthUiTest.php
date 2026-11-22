<?php

namespace Tests\Feature\Ui;

use App\Models\Plant;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** K3: nền tảng giao diện (CSS dùng chung, header/footer, trang tài khoản, mẫu mail). */
class LayoutAndAuthUiTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_shared_stylesheet_keeps_every_legacy_class(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));
        // 48 view đang dùng các class này: đổi tên / xóa là vỡ giao diện hàng loạt
        foreach (['.btn-green', '.btn-outline-green', '.text-green', '.bg-green', '.plant-card', '.hero', '.footer'] as $class) {
            $this->assertStringContainsString($class, $css, "app.css thiếu class cũ {$class}");
        }
    }

    public function test_auth_pages_use_the_new_layout(): void
    {
        foreach (['login', 'register', 'password.request'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('/css/app.css?v=', $html);
            $this->assertStringContainsString('gg-auth', $html);
            $this->assertStringContainsString('Hạng thành viên', $html);
        }
        $this->get(route('password.reset', ['token' => 't']))->assertOk()->assertSee('gg-auth', false);
    }

    /** Script trình duyệt bấm "form[action$=dang-nhap] button": nút ĐẦU TIÊN trong form đăng nhập phải là nút gửi. */
    public function test_login_form_has_a_single_submit_button_and_a_show_password_checkbox(): void
    {
        $html = $this->get(route('login'))->getContent();
        preg_match('#<form action="[^"]*dang-nhap" method="POST"[^>]*>(.*?)</form>#s', $html, $m);
        $this->assertNotEmpty($m, 'Không tìm thấy form đăng nhập');
        $this->assertSame(1, substr_count($m[1], '<button'), 'Form đăng nhập chỉ được có 1 nút (nút gửi)');
        $this->assertStringContainsString('data-show-password', $m[1]);
        $this->assertStringContainsString('data-password', $m[1]);
    }

    public function test_header_free_shipping_comes_from_config_and_nav_marks_current_page(): void
    {
        config(['shop.shipping.free_min_order' => 777000]);
        $html = $this->get(route('plants.index'))->assertOk()->getContent();
        $this->assertStringContainsString('777.000đ', $html);
        $this->assertMatchesRegularExpression('#class="nav-link active"[^>]*href="'.preg_quote(route('plants.index'), '#').'"#', $html);
    }

    public function test_logged_in_header_shows_avatar_and_account_menu(): void
    {
        $html = $this->actingAs($this->customer())->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('<span class="gg-avatar">N</span>', $html);
        foreach (['Hồ sơ của tôi', 'Đơn hàng của tôi', 'Hạng thành viên', 'Sổ địa chỉ', 'Đăng xuất'] as $item) {
            $this->assertStringContainsString($item, $html);
        }
        $this->assertStringNotContainsString('Trang quản trị', $html);
        $this->assertStringContainsString('Trang quản trị', $this->actingAs($this->admin())->get(route('home'))->getContent());
    }

    /** K7a: thẻ cây báo tồn kho + sao từ cột có sẵn của cây; cam kết trang chủ lấy từ cấu hình (đổi config là đổi theo). */
    public function test_storefront_cards_and_promises(): void
    {
        config(['shop.return_window_days' => 9, 'shop.shipping.free_min_order' => 650000]);
        $home = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('Đổi trả 9 ngày', $home);
        $this->assertStringContainsString('650.000đ', $home);
        $this->assertStringContainsString('id="chon-theo-menh"', $home);

        $plant = Plant::findOrFail(1);
        DB::table('plants')->where('id', 1)->update(['rating_sum' => 9, 'rating_count' => 2, 'reorder_point' => $plant->stock]);
        $card = view('partials.plant-card', ['plant' => Plant::with('category')->findOrFail(1)])->render();
        $this->assertStringContainsString('Sắp hết hàng', $card);
        $this->assertStringContainsString('(2)', $card, 'Số lượt đánh giá');
        $this->assertStringContainsString('title="4.5/5"', $card);

        DB::table('plants')->where('id', 1)->update(['rating_count' => 0, 'rating_sum' => 0, 'reorder_point' => 0]);
        $card = view('partials.plant-card', ['plant' => Plant::with('category')->findOrFail(1)])->render();
        $this->assertStringNotContainsString('Sắp hết hàng', $card);
        $this->assertStringNotContainsString('gg-stars', $card);
    }

    /** Thương hiệu thống nhất là GreenGarden: không còn tên cũ ở bất cứ chỗ nào khách nhìn thấy (giao diện, mail, cấu hình). */
    public function test_no_old_brand_name_in_customer_facing_code(): void
    {
        $leaks = [];
        foreach (['app', 'resources', 'config', 'lang'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (preg_match('/GreenLife|GREENLIFE/', (string) file_get_contents($file->getPathname()))) {
                    $leaks[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }
        $this->assertSame([], $leaks, 'Còn tên thương hiệu cũ "GreenLife"');
        $this->assertSame('GreenGarden Shop', config('app.name'));
    }

    public function test_mail_template_is_branded_and_never_shows_laravel(): void
    {
        $user = $this->customer();
        foreach (['GreenGarden Shop', 'Laravel'] as $appName) {          // "Laravel": máy có .env chưa đổi APP_NAME
            config(['app.name' => $appName]);
            $html = (string) (new ResetPassword('t'))->toMail($user)->render();
            $this->assertStringContainsString('GreenGarden Shop', $html);
            $this->assertStringNotContainsString('Laravel', $html);
            $this->assertStringContainsString('Hotline 0900 000 001', $html);
            $this->assertStringContainsString('#2e7d32', $html, 'Nút / viền mail phải theo màu thương hiệu');
        }
    }
}

<?php

namespace App\Providers;

use App\Enums\PaymentMethod;
use App\Models\Article;
use App\Models\Plant;
use App\Models\User;
use App\Services\CartService;
use App\Services\Payments\DisabledGateway;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\VnpayGateway;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cổng thanh toán online: 'vnpay' (sandbox / thật), 'fake' (CHỈ local/testing), còn lại: tắt hẳn
        $this->app->bind(PaymentGateway::class, function () {
            $cfg = config('shop.vnpay');

            return match (PaymentMethod::onlineGatewayMode()) {
                'vnpay' => new VnpayGateway((string) $cfg['tmn_code'], (string) $cfg['hash_secret'], (string) $cfg['url'], (string) $cfg['version']),
                'fake' => new FakeGateway((string) config('app.key'), url('/thanh-toan/gia-lap')),
                default => new DisabledGateway,
            };
        });
    }

    public function boot(): void
    {
        // Sau reverse proxy: thiếu bước này thì mọi khách chung IP của proxy -> giới hạn đăng nhập 20 lần/phút
        // áp cho CẢ site, IP gửi VNPay sai, và HTTPS bị nhận là HTTP (mất HSTS, link sinh ra là http://).
        $proxies = trim((string) config('shop.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // /up (health check cho load balancer / uptime monitor): kiểm tra luôn kết nối DB, không chỉ PHP còn sống
        Event::listen(DiagnosingHealth::class, fn () => DB::select('SELECT 1'));

        Paginator::useBootstrapFive();

        // Đăng nhập: 5 lần / phút / (email + IP) và 20 lần / phút / IP
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Ngoài production: báo lỗi ngay khi có truy vấn N+1 (lazy loading) để test bắt được
        Model::preventLazyLoading(! $this->app->isProduction());

        // @money($v) -> 250.000đ   |   @vndt($date) -> giờ Việt Nam
        Blade::directive('money', fn ($e) => "<?php echo e(\\App\\Support\\Format::money({$e})); ?>");
        Blade::directive('vndt', fn ($e) => "<?php echo e(\\App\\Support\\Format::datetime({$e})); ?>");

        // Số loại cây trong giỏ cho thanh menu
        View::composer('layouts.app', fn ($view) => $view->with('cartCount', app(CartService::class)->count()));
        // Lưu 'plant' thay vì 'App\Models\Plant' trong comments.commentable_type.
        // (Sổ kho đã dùng FK thật order_item_id / goods_receipt_item_id, không còn quan hệ đa hình.)
        Relation::enforceMorphMap([
            'plant' => Plant::class,
            'article' => Article::class,
            'user' => User::class,
        ]);

        // Mail đặt lại mật khẩu tiếng Việt (link hết hạn theo auth.passwords.users.expire)
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Đặt lại mật khẩu - GreenLife')
                ->greeting('Xin chào '.$notifiable->name.'!')
                ->line('Bạn nhận được email này vì có yêu cầu đặt lại mật khẩu cho tài khoản GreenLife của bạn.')
                ->action('Đặt mật khẩu mới', url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)))
                ->line("Liên kết chỉ dùng được một lần và hết hạn sau {$minutes} phút.")
                ->line('Nếu bạn không yêu cầu, hãy bỏ qua email này: mật khẩu hiện tại vẫn giữ nguyên.')
                ->salutation('Trân trọng, GreenLife Shop');
        });

        // Nội dung mail xác thực bằng tiếng Việt (mặc định Laravel gửi tiếng Anh)
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Xác thực địa chỉ email - GreenLife')
                ->greeting('Xin chào '.$notifiable->name.'!')
                ->line('Cảm ơn bạn đã đăng ký tài khoản tại GreenLife Shop.')
                ->line('Vui lòng bấm nút bên dưới để xác thực email. Sau khi xác thực, bạn có thể đặt hàng.')
                ->action('Xác thực email', $url)
                ->line('Liên kết có hiệu lực trong 60 phút.')
                ->line('Nếu bạn không đăng ký tài khoản, hãy bỏ qua email này.')
                ->salutation('Trân trọng, GreenLife Shop');
        });
    }
}

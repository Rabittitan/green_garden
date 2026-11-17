<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator; 
use App\Models\Article;
use App\Models\GoodsReceipt;
use App\Models\Order;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {

        Paginator::useBootstrapFive(); 
        // Lưu 'plant' thay vì 'App\Models\Plant' trong các cột *_type (comments, stock_movements).
        Relation::enforceMorphMap([
            'plant' => Plant::class,
            'article' => Article::class,
            'order' => Order::class,
            'goods_receipt' => GoodsReceipt::class,
            'user' => User::class,
        ]);

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
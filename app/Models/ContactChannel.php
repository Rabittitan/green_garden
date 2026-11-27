<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Một kênh liên hệ của shop (bong bóng mạng xã hội góc phải trang khách). Admin → Kênh liên hệ.
 *
 * @property int $id
 * @property string $channel
 * @property string $url
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContactChannel extends Model
{
    /** Kênh hỗ trợ: tên hiển thị, gợi ý link, màu nút. Thứ tự mảng = thứ tự mặc định trên bong bóng. */
    public const CHANNELS = [
        'zalo' => ['label' => 'Zalo', 'placeholder' => 'https://zalo.me/0900000001', 'color' => '#0068ff'],
        'facebook' => ['label' => 'Facebook', 'placeholder' => 'https://www.facebook.com/tentrang', 'color' => '#1877f2'],
        'tiktok' => ['label' => 'TikTok', 'placeholder' => 'https://www.tiktok.com/@tenkenh', 'color' => '#111111'],
    ];

    /** Tên miền hợp lệ cho từng kênh: chặn dán nhầm / dán link lừa đảo vào nút mang logo kênh. */
    public const HOSTS = [
        'zalo' => ['zalo.me', 'chat.zalo.me', 'oa.zalo.me'],
        'facebook' => ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com', 'fb.com', 'www.fb.com', 'fb.me', 'm.me'],
        'tiktok' => ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com', 'vt.tiktok.com', 'vm.tiktok.com'],
    ];

    protected $fillable = ['channel', 'url', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function label(): string
    {
        return self::CHANNELS[$this->channel]['label'] ?? $this->channel;
    }

    public function color(): string
    {
        return self::CHANNELS[$this->channel]['color'] ?? '#2e7d32';
    }

    public static function allowsHost(string $channel, string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with(strtolower($url), 'https://') && in_array($host, self::HOSTS[$channel] ?? [], true);
    }
}

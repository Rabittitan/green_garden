<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ContactChannel;
use Illuminate\Support\Facades\DB;

/** Lưu link Zalo / Facebook / TikTok của bong bóng mạng xã hội. Để trống link = bỏ kênh đó. */
class ContactChannelService
{
    /**
     * @param  array<string, array{url?: string|null, is_active?: bool, sort_order?: int}>  $rows  kênh => dữ liệu
     */
    public function save(array $rows): void
    {
        foreach ($rows as $channel => $row) {
            $url = trim((string) ($row['url'] ?? ''));
            if (! array_key_exists($channel, ContactChannel::CHANNELS)) {
                throw new BusinessException('Kênh liên hệ không hợp lệ.');
            }
            if ($url !== '' && ! ContactChannel::allowsHost($channel, $url)) {
                $label = ContactChannel::CHANNELS[$channel]['label'];
                throw new BusinessException("Link {$label} phải là https:// và thuộc ".implode(', ', ContactChannel::HOSTS[$channel]).'.');
            }
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $channel => $row) {
                $url = trim((string) ($row['url'] ?? ''));
                if ($url === '') {
                    ContactChannel::where('channel', $channel)->delete();

                    continue;
                }
                ContactChannel::updateOrCreate(['channel' => $channel], [
                    'url' => $url, 'is_active' => (bool) ($row['is_active'] ?? false), 'sort_order' => (int) ($row['sort_order'] ?? 0),
                ]);
            }
        }, 3);
    }
}

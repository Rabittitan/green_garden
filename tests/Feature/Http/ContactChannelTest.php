<?php

namespace Tests\Feature\Http;

use App\Models\ContactChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Bong bóng mạng xã hội (góp ý 26/11): admin nhập link Zalo / Facebook / TikTok, khách bấm nút tròn thấy các kênh. */
class ContactChannelTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return ['channels' => array_replace_recursive([
            'zalo' => ['url' => 'https://zalo.me/0912345678', 'is_active' => 1, 'sort_order' => 0],
            'facebook' => ['url' => 'https://www.facebook.com/caycanhgg', 'is_active' => 1, 'sort_order' => 1],
            'tiktok' => ['url' => 'https://www.tiktok.com/@caycanhgg', 'is_active' => 1, 'sort_order' => 2],
        ], $over)];
    }

    public function test_bubble_lists_active_channels_on_customer_pages(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();                         // 3 kênh mẫu
        $this->assertStringContainsString('data-testid="social-bubble"', $html);
        $this->assertSame(3, substr_count($html, 'class="gg-social-item"'));
        $this->assertStringContainsString('href="https://zalo.me/0900000001" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertLessThan(strpos($html, 'facebook.com/greengarden.demo'), strpos($html, 'zalo.me/0900000001'));   // theo thứ tự

        ContactChannel::where('channel', 'tiktok')->update(['is_active' => false]);
        $html = $this->actingAs($this->customer())->get(route('plants.index'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'class="gg-social-item"'));
        $this->assertStringNotContainsString('tiktok.com', $html);

        ContactChannel::query()->update(['is_active' => false]);
        $this->get(route('home'))->assertDontSee('data-testid="social-bubble"', false);       // không kênh nào: không có nút
        $this->actingAs($this->admin())->get(route('admin.orders.index'))->assertDontSee('gg-social-item', false);   // không hiện ở trang quản trị
    }

    public function test_staff_with_content_permission_saves_links(): void
    {
        $this->actingAs(User::factory()->staff()->create(['permissions' => ['content']]));
        $this->get(route('admin.contact-channels.index'))->assertOk()->assertSee('https://zalo.me/0900000001', false);
        $this->put(route('admin.contact-channels.update'), $this->form(['tiktok' => ['is_active' => 0, 'sort_order' => 5]]))
            ->assertRedirect(route('admin.contact-channels.index'))->assertSessionHas('success');
        $rows = ContactChannel::orderBy('channel')->get()->keyBy('channel');
        $this->assertSame('https://zalo.me/0912345678', $rows['zalo']->url);
        $this->assertSame([false, 5], [$rows['tiktok']->is_active, $rows['tiktok']->sort_order]);

        $this->put(route('admin.contact-channels.update'), $this->form(['facebook' => ['url' => '']]));   // để trống = bỏ kênh
        $this->assertFalse(ContactChannel::where('channel', 'facebook')->exists());
    }

    public function test_links_must_be_https_on_the_channels_own_domain(): void
    {
        $this->actingAs($this->admin());
        foreach (['http://zalo.me/0912345678', 'https://zalo.me.lua-dao.vn/x', 'https://bit.ly/abc', 'javascript:alert(1)'] as $bad) {
            $this->put(route('admin.contact-channels.update'), $this->form(['zalo' => ['url' => $bad]]))->assertSessionHas('error');
            $this->assertSame('https://zalo.me/0900000001', ContactChannel::where('channel', 'zalo')->value('url'), $bad);
        }
        $this->put(route('admin.contact-channels.update'), $this->form(['facebook' => ['url' => 'https://www.tiktok.com/@x']]))->assertSessionHas('error');
        $this->put(route('admin.contact-channels.update'), $this->form(['facebook' => ['url' => 'https://m.me/caycanhgg']]))->assertSessionHas('success');
    }

    public function test_other_roles_cannot_edit(): void
    {
        $this->actingAs(User::factory()->staff()->create(['permissions' => ['orders']]))
            ->put(route('admin.contact-channels.update'), $this->form())->assertNoAccess();
        $this->actingAs($this->customer())->get(route('admin.contact-channels.index'))->assertNoAccess();
        $this->assertSame('https://zalo.me/0900000001', ContactChannel::where('channel', 'zalo')->value('url'));
    }
}

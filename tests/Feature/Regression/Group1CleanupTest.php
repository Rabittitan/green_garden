<?php

namespace Tests\Feature\Regression;

use App\Exceptions\BusinessException;
use App\Models\Article;
use App\Models\MembershipTier;
use App\Models\User;
use App\Models\UserAddress;
use App\Notifications\Admin\ReconcileFailed;
use App\Notifications\OrderReceived;
use App\Services\AddressService;
use App\Services\ArticleService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * Nhóm 1 sau rà soát kiến trúc (27/11): đối soát lệch phải BÁO quản trị viên (trước chỉ ghi log), mail đặt hàng đi qua Notifier
 * (trước là đường riêng, tiêu đề ghi sai thương hiệu "GardenLife"), ảnh chèn trong bài bị gỡ thì xóa file (trước để mồ côi),
 * và các luồng ghi trước nằm trong controller nay qua service giữ đúng hành vi.
 */
class Group1CleanupTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_reconcile_mismatch_emails_every_active_admin(): void
    {
        Notification::fake();
        $this->artisan('shop:reconcile')->assertExitCode(0);
        Notification::assertNothingSent();                                            // khớp: không làm phiền ai

        $sleeping = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        DB::table('plants')->where('id', 2)->update(['rating_sum' => 99, 'rating_count' => 3]);   // lệch điểm đánh giá
        $this->artisan('shop:reconcile')->assertExitCode(1);

        Notification::assertSentTo($this->admin(), ReconcileFailed::class, fn (ReconcileFailed $n) => $n->failures === ['Điểm đánh giá lệch' => [2]]);
        Notification::assertNotSentTo([$sleeping, $this->customer()], ReconcileFailed::class);
        $mail = (new ReconcileFailed(['Điểm đánh giá lệch' => [2]], now()))->toMail($this->admin());
        $this->assertStringContainsString('[Cần xử lý]', $mail->subject);
        $this->assertContains('• Điểm đánh giá lệch: 2', $mail->introLines);
    }

    public function test_order_confirmation_goes_through_notifier(): void
    {
        Notification::fake();
        $this->actingAs($this->customer())->post(route('cart.add', 1))->assertSessionHas('success');
        $this->get(route('checkout.form'))->assertOk();
        $this->post(route('checkout.store'), array_merge($this->shipping(), ['payment_method' => 'cod',
            'checkout_token' => session('checkout.token'), 'expected_total' => 280000]))->assertRedirect();
        Notification::assertSentTo($this->customer(), OrderReceived::class);
    }

    public function test_inline_article_images_removed_from_content_are_deleted_unless_used_elsewhere(): void
    {
        Storage::fake('public');
        $articles = app(ArticleService::class);
        $up = fn () => $articles->storeImage(UploadedFile::fake()->image('a.jpg'));
        [$a, $shared, $kept, $gone] = [$up(), $up(), $up(), $up()];
        $other = Article::firstOrFail();
        $other->forceFill(['content' => $other->content."\n\n![x]({$shared})"])->save();          // bài khác cũng dùng ảnh này
        $data = fn (string $content) => ['title' => 'Bài ảnh', 'summary' => 'Tóm tắt', 'content' => $content, 'status' => 'draft'];
        $post = $articles->create($this->admin(), $data("![1]({$a})\n\n![2]({$shared})\n\n![3]({$kept})"));

        $articles->update($post, $data("Chỉ còn ![3]({$kept}) và ![4]({$gone})"));
        $disk = Storage::disk('public');
        $disk->assertMissing(substr($a, 9));                                                        // gỡ khỏi bài, không ai dùng -> xóa
        $disk->assertExists(substr($shared, 9));                                                    // bài khác còn dùng -> giữ
        $disk->assertExists(substr($kept, 9));
        $disk->assertExists(substr($gone, 9));

        $articles->delete($post->refresh());
        $disk->assertMissing(substr($kept, 9));
        $disk->assertMissing(substr($gone, 9));
        $disk->assertExists(substr($shared, 9));
    }

    public function test_address_book_rules_hold_through_the_service(): void
    {
        $user = User::factory()->create();
        $addresses = app(AddressService::class);
        $fields = fn (string $line) => array_merge($this->shipping(), ['address_line' => $line]);

        $addresses->rememberFromOrder($user->id, $fields('1 Lê Lợi'));
        $addresses->rememberFromOrder($user->id, $fields('1 Lê Lợi'));                           // trùng: không lưu thêm
        $this->assertSame([1, true], [$user->addresses()->count(), (bool) $user->addresses()->sole()->is_default]);
        $second = $addresses->create($user, $fields('2 Lê Lợi'), true);                            // địa chỉ mới làm mặc định
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->assertTrue($second->refresh()->is_default);
        $addresses->delete($second);                                                                // xóa mặc định -> địa chỉ còn lại thành mặc định
        $this->assertTrue($user->addresses()->sole()->is_default);

        for ($i = $user->addresses()->count(); $i < UserAddress::MAX_PER_USER; $i++) {
            $addresses->create($user, $fields("Số {$i}"), false);
        }
        $addresses->rememberFromOrder($user->id, $fields('Sổ đầy'));                               // sổ đầy: bỏ qua, không lỗi
        $this->assertSame(UserAddress::MAX_PER_USER, $user->addresses()->count());
        $this->expectException(BusinessException::class);
        $addresses->create($user, $fields('Quá trần'), false);
    }

    public function test_tier_config_still_requires_increasing_thresholds(): void
    {
        $tiers = MembershipTier::ordered()->get();
        $data = $tiers->mapWithKeys(fn ($t) => [$t->id => ['name' => $t->name, 'min_spend' => $t->rank === 0 ? 0 : 1000, 'discount_percent' => $t->discount_percent]])->all();
        try {
            app(MembershipService::class)->saveTierConfig($data);                                 // các hạng trên cùng ngưỡng 1000
            $this->fail('Ngưỡng không tăng dần phải bị chặn');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('phải lớn hơn hạng thấp hơn', $e->getMessage());
        }
        $this->assertSame($tiers->pluck('min_spend')->all(), MembershipTier::ordered()->pluck('min_spend')->all());   // không đổi gì
    }
}

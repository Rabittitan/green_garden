<?php

namespace Tests\Feature\Http;

use App\Models\Article;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Quản lý tin tức: nhân viên/quản trị đăng bài, ảnh bìa + ảnh trong bài, nháp / hẹn giờ, nội dung an toàn. */
class ArticleAdminTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function payload(array $extra = []): array
    {
        return array_merge(['title' => 'Cách thay chậu cho cây', 'summary' => 'Thay chậu đúng lúc giúp cây lớn khỏe.',
            'content' => "Thay chậu vào mùa xuân.\n\n## Chuẩn bị\n\n- Chậu mới\n- Đất tơi xốp", 'status' => 'published'], $extra);
    }

    public function test_staff_publishes_with_uploaded_cover_and_plants_and_it_shows_on_the_site(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->post(route('admin.articles.store'), $this->payload([
            'cover' => UploadedFile::fake()->image('bia.jpg', 1200, 600), 'plant_ids' => [1, 3],
        ]))->assertSessionHas('success', 'Đã đăng bài.');

        $a = Article::where('title', 'Cách thay chậu cho cây')->sole();
        $this->assertSame([$staff->id, 'published'], [$a->author_id, $a->status]);
        $this->assertMatchesRegularExpression('#^/storage/articles/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#', $a->image);   // tên file do server đặt
        Storage::disk('public')->assertExists(substr($a->image, strlen('/storage/')));
        $this->assertSame([1, 3], $a->plants()->pluck('plants.id')->sort()->values()->all());

        auth()->logout();
        $this->get(route('news.index'))->assertSee('Cách thay chậu cho cây');
        $this->get(route('news.show', $a->id))->assertOk()
            ->assertSee('<h2>Chuẩn bị</h2>', false)->assertSee('<li>Chậu mới</li>', false)        // Markdown
            ->assertSee('src="'.$a->image.'"', false)->assertSee('Cây được nhắc tới trong bài')->assertSee($staff->name);
    }

    public function test_draft_and_scheduled_posts_stay_hidden_until_due(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.articles.store'), $this->payload(['title' => 'Bài nháp', 'status' => 'draft']))
            ->assertSessionHas('success', 'Đã lưu nháp. Bài chưa hiện ở trang tin tức.');
        // Giờ đăng nhập theo giờ Việt Nam: 01/12/2026 08:00 (VN) = 01:00 UTC
        $this->post(route('admin.articles.store'), $this->payload(['title' => 'Bài hẹn giờ', 'published_at' => '2026-12-01T08:00']))
            ->assertSessionHas('success', 'Đã hẹn giờ: bài tự hiện lúc 01/12/2026 08:00.');
        $draft = Article::where('title', 'Bài nháp')->sole();
        $scheduled = Article::where('title', 'Bài hẹn giờ')->sole();
        $this->assertSame('2026-12-01 01:00:00', $scheduled->published_at->utc()->format('Y-m-d H:i:s'));

        auth()->logout();
        $this->get(route('news.index'))->assertDontSee('Bài nháp')->assertDontSee('Bài hẹn giờ');
        $this->get(route('news.show', $draft->id))->assertNotFound();
        $this->get(route('news.show', $scheduled->id))->assertNotFound();

        $this->actingAs($this->admin())->get(route('admin.articles.show', $draft))->assertOk()->assertSee('Xem trước')->assertSee('Nháp');
        $this->get(route('admin.articles.index', ['status' => 'scheduled']))->assertSee('Bài hẹn giờ')->assertDontSee('Bài nháp');

        Carbon::setTestNow(Carbon::parse('2026-12-01 01:00:01', 'UTC'));                   // tới giờ: tự hiện, không cần job
        auth()->logout();
        $this->get(route('news.show', $scheduled->id))->assertOk();
    }

    public function test_content_is_safe_markdown(): void
    {
        $this->actingAs($this->admin())->post(route('admin.articles.store'), $this->payload([
            'content' => "**Đậm** <script>alert(1)</script>\n<img src=x onerror=alert(2)>\n\n[bấm](javascript:alert(3)) ![ảnh](/storage/articles/a.jpg)",
        ]));
        $html = $this->get(route('news.show', Article::latest('id')->first()->id))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<div class="gg-article-body">(.*?)</div>#s', $html, $m));
        $this->assertStringContainsString('<strong>Đậm</strong>', $m[1]);
        $this->assertStringNotContainsString('<script', $m[1]);
        $this->assertStringNotContainsString('onerror', $m[1]);
        $this->assertStringNotContainsString('javascript:', $m[1]);
        $this->assertStringContainsString('<img loading="lazy" class="img-fluid rounded-3" src="/storage/articles/a.jpg" alt="ảnh"', $m[1]);
    }

    public function test_old_plain_text_articles_render_as_before(): void
    {
        $a = Article::published()->firstOrFail();                                           // bài mẫu: chữ thuần, đoạn cách 1 dòng trống
        $paragraphs = array_values(array_filter(explode("\n\n", $a->content)));
        $html = (string) $a->renderedContent();
        $this->assertSame(count($paragraphs), substr_count($html, '<p>'));
        $this->assertStringContainsString(e($paragraphs[0]), $html);
    }

    public function test_inline_image_upload_returns_markdown_and_rejects_non_images(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $res = $this->postJson(route('admin.articles.images'), ['image' => UploadedFile::fake()->image('trong-bai.png', 800, 600)])->assertOk();
        $this->assertMatchesRegularExpression('#^!\[Mô tả ảnh\]\(/storage/articles/\d{4}/\d{2}/[0-9a-f-]{36}\.png\)$#', $res->json('markdown'));
        Storage::disk('public')->assertExists(substr($res->json('url'), strlen('/storage/')));

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->postJson(route('admin.articles.images'), ['image' => $svg])->assertStatus(422);
        $php = UploadedFile::fake()->createWithContent('anh.jpg', '<?php echo 1;');                  // đổi đuôi không qua được
        $this->postJson(route('admin.articles.images'), ['image' => $php])->assertStatus(422);
        $this->postJson(route('admin.articles.images'), ['image' => UploadedFile::fake()->image('to.jpg')->size(5000)])->assertStatus(422);
        $this->assertCount(1, Storage::disk('public')->allFiles('articles'));
    }

    public function test_replacing_or_removing_cover_deletes_the_old_uploaded_file(): void
    {
        $this->actingAs($this->admin())->post(route('admin.articles.store'), $this->payload(['cover' => UploadedFile::fake()->image('a.jpg')]));
        $a = Article::latest('id')->first();
        $first = substr($a->image, strlen('/storage/'));

        $this->put(route('admin.articles.update', $a), $this->payload(['cover' => UploadedFile::fake()->image('b.png')]))->assertSessionHas('success');
        Storage::disk('public')->assertMissing($first);
        $second = substr($a->refresh()->image, strlen('/storage/'));
        Storage::disk('public')->assertExists($second);

        $this->put(route('admin.articles.update', $a), $this->payload(['remove_cover' => 1]))->assertSessionHas('success');
        $this->assertNull($a->refresh()->image);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_validation_limits_and_delete_rules(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.articles.store'), $this->payload(['title' => '', 'summary' => str_repeat('x', 301),
            'content' => str_repeat('ệ', 20001), 'status' => 'xoa', 'image_url' => 'http://khong-an-toan.vn/a.jpg']))
            ->assertSessionHasErrors(['title', 'summary', 'content', 'status', 'image_url']);
        // 20.000 ký tự tiếng Việt (60.000 byte) vẫn vừa cột TEXT
        $this->post(route('admin.articles.store'), $this->payload(['title' => 'Bài dài', 'content' => str_repeat('ệ', 20000)]))->assertSessionHasNoErrors();

        $a = Article::where('title', 'Bài dài')->sole();
        app(CommentService::class)->post($this->customer(), $a, 'Bài hay');
        $this->delete(route('admin.articles.destroy', $a))->assertSessionHas('error');                // có bình luận: không xóa hẳn
        $this->assertNotNull($a->fresh());
        $this->put(route('admin.articles.update', $a), $this->payload(['title' => 'Bài dài', 'content' => 'x', 'status' => 'draft']));
        auth()->logout();
        $this->get(route('news.show', $a->id))->assertNotFound();                                   // chuyển về nháp = gỡ khỏi trang

        $this->actingAs($this->admin())->post(route('admin.articles.store'), $this->payload(['title' => 'Xóa được']));
        $b = Article::where('title', 'Xóa được')->sole();
        $this->delete(route('admin.articles.destroy', $b))->assertRedirect(route('admin.articles.index'));
        $this->assertNull($b->fresh());
    }

    public function test_customers_cannot_reach_article_admin(): void
    {
        $this->actingAs($this->customer())->get(route('admin.articles.index'))->assertNoAccess();
        $this->post(route('admin.articles.store'), $this->payload())->assertNoAccess();
        $this->postJson(route('admin.articles.images'), ['image' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }
}

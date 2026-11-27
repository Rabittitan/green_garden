<?php

namespace Tests\Feature\Regression;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lỗi chủ shop báo 26/11: "thêm bài viết, ảnh đã tải lên nhưng không hiện".
 * Nguyên nhân: máy chưa có link public/storage (quên `php artisan storage:link`, hoặc Windows không cho tạo link),
 * nên /storage/... rơi vào route mặc định của Laravel cho disk RIÊNG TƯ "local" (đòi chữ ký) -> 403, ảnh vỡ.
 * Sửa: disk "public" tự phát file khi thiếu link; disk "local" thôi chiếm đường /storage.
 */
class UploadedImagesWithoutStorageLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_article_image_is_served_even_without_the_storage_link(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->staff()->create());
        $url = $this->postJson(route('admin.articles.images'), ['image' => UploadedFile::fake()->image('anh.png', 40, 30)])->assertOk()->json('url');

        $res = $this->get($url)->assertOk();                                   // code cũ: 403
        $this->assertSame('image/png', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('sandbox', (string) $res->headers->get('Content-Security-Policy'));   // file tải lên không chạy được mã
    }

    public function test_only_public_uploads_are_reachable(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('bi-mat.txt', 'không được lộ');            // file riêng tư: không có đường công khai

        $this->get('/storage/bi-mat.txt')->assertNotFound();
        $this->get('/storage/articles/khong-co.png')->assertNotFound();
        $this->get('/storage/..%2F..%2F.env')->assertNotFound();
        $this->put('/storage/articles/ghi-de.png', [])->assertForbidden();    // tải lên trực tiếp cần link ký -> chặn (route không phiên: vẫn 403)
        Storage::disk('public')->assertMissing('articles/ghi-de.png');
    }
}

<?php

namespace Tests;

use App\Support\NoAccessRedirect;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

abstract class TestCase extends BaseTestCase
{
    /**
     * Luôn seed dữ liệu mẫu khi RefreshDatabase dựng DB. Đặt ở lớp cha vì RefreshDatabase chỉ
     * migrate + seed MỘT lần cho cả tiến trình: nếu lớp test đầu tiên không seed thì mọi lớp sau thiếu dữ liệu.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        /*
         * Không có quyền trên trang web = chuyển về trang đầu của vai trò + thông báo nổi (App\Support\NoAccessRedirect).
         * $home: route mong đợi ('home' cho khách, 'admin.dashboard' cho nhân viên / quản trị); null = không kiểm đích.
         */
        TestResponse::macro('assertNoAccess', function (?string $home = null) {
            /** @var TestResponse $this */
            $this->assertRedirect($home !== null ? route($home) : null);
            Assert::assertSame(NoAccessRedirect::DENIED, $this->session()->get('error'), 'Phải báo "không có quyền".');

            return $this;
        });
    }

    /** Bị chặn vì thiếu quyền: 403 (gọi JSON) hoặc bị chuyển về trang đầu kèm thông báo không có quyền (trang web). */
    protected function isDenied(TestResponse $response): bool
    {
        return $response->getStatusCode() === 403
            || ($response->isRedirect() && app('session.store')->get('error') === NoAccessRedirect::DENIED);
    }
}

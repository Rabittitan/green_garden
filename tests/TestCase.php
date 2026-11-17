<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Luôn seed dữ liệu mẫu khi RefreshDatabase dựng DB. Đặt ở lớp cha vì RefreshDatabase chỉ
     * migrate + seed MỘT lần cho cả tiến trình: nếu lớp test đầu tiên không seed thì mọi lớp sau thiếu dữ liệu.
     */
    protected bool $seed = true;
}

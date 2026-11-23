<?php

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * Bảng admin trên điện thoại. Bố cục thật được đo bằng trình duyệt (roles.e2e.cjs: 10 trang ở 390px không tràn ngang);
 * test này giữ các "móc" mà phép đo đó dựa vào, để không ai vô tình gỡ mất.
 */
class AdminTablesTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_order_list_stacks_into_cards_with_a_label_for_every_cell(): void
    {
        $this->place(cart: [1 => 1]);
        $this->place(cart: [3 => 1]);
        $html = $this->actingAs($this->admin())->get(route('admin.orders.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<table class="[^"]*gg-table-stack[^"]*">.*?<tbody>(.*?)</tbody>#s', $html, $m));
        $this->assertSame(16, substr_count($m[1], '<td'));                              // 2 đơn × 8 cột
        $this->assertSame(16, substr_count($m[1], 'data-label="'));                     // ô nào cũng có nhãn cho dạng thẻ
        $this->assertStringContainsString('data-label="Tổng tiền"', $m[1]);
        $this->assertStringContainsString('Thanh toán khi nhận hàng', $m[1]);           // tên phương thức, không phải mã "cod"
    }

    public function test_shared_css_keeps_tables_inside_the_screen_without_bootstrap(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.table-responsive { overflow-x: auto;', $css);
        $this->assertStringContainsString('.gg-admin-content table.table:not(.gg-table-stack) { display: block; max-width: 100%; overflow-x: auto;', $css);
        $this->assertStringContainsString('.gg-admin-main { min-width: 0; }', $css);
        $this->assertStringContainsString('.gg-table-stack td::before { content: attr(data-label);', $css);
    }
}

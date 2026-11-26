<?php

namespace Tests\Feature\Http;

use App\Enums\ActorType;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** In hóa đơn bán lẻ (khách + nhân viên) và ghi chú của khách cho shop. */
class OrderInvoiceAndNoteTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_customer_prints_invoice_with_items_totals_and_note(): void
    {
        config(['shop.seller' => ['name' => 'GreenGarden Shop', 'address' => '1 Lê Lợi, TP.HCM', 'phone' => '0900 000 001', 'email' => '', 'tax_code' => '0312345678']]);
        $order = $this->place(cart: [1 => 2, 3 => 1]);
        app(OrderService::class)->updateNote($order, $this->customer(), 'Giao giờ hành chính');

        $this->actingAs($this->customer())->get(route('orders.show', $order))->assertOk()
            ->assertSee('href="'.route('orders.invoice', $order).'"', false);
        $html = $this->get(route('orders.invoice', $order))->assertOk()->getContent();
        foreach (['HÓA ĐƠN BÁN LẺ', $order->code, 'Cây Kim Tiền', 'Cây Lưỡi Hổ', Format::money($order->total),
            '1 Lê Lợi, TP.HCM', 'Mã số thuế: 0312345678', 'Giao giờ hành chính', 'Còn phải thu khi giao (COD)',
            'không thay thế hóa đơn giá trị gia tăng'] as $text) {
            $this->assertStringContainsString($text, $html, "Hóa đơn thiếu: {$text}");
        }
        $this->assertStringNotContainsString('Email:', $html);                                 // dòng người bán để trống thì không in
        $this->assertStringNotContainsString('cdn.jsdelivr', $html);                           // in được cả khi CDN lỗi
    }

    public function test_invoice_marks_cancelled_orders_and_is_owner_or_staff_only(): void
    {
        $order = $this->place();
        app(OrderService::class)->cancel($order, ActorType::Customer, $this->customer(), 'Đổi ý');
        $this->actingAs($this->customer())->get(route('orders.invoice', $order))->assertOk()->assertSee('ĐƠN ĐÃ HỦY')
            ->assertDontSee('Còn phải thu');

        $this->actingAs(User::factory()->create())->get(route('orders.invoice', $order))->assertNoAccess();
        $this->actingAs(User::factory()->staff()->create())->get(route('admin.orders.invoice', $order))->assertOk()->assertSee($order->code);
        $this->actingAs($this->customer())->get(route('admin.orders.invoice', $order))->assertNoAccess();
        auth()->logout();
        $this->get(route('orders.invoice', $order))->assertRedirect(route('login'));
    }

    public function test_customer_can_edit_note_until_the_shop_confirms(): void
    {
        $order = $this->place();
        $this->actingAs($this->customer())->get(route('orders.show', $order))->assertSee('id="order-note"', false);
        $this->post(route('orders.note', $order), ['note' => "Gọi trước khi giao\nGói làm quà"])->assertSessionHas('success');
        $this->assertSame("Gọi trước khi giao\nGói làm quà", $order->fresh()->note);
        $this->post(route('orders.note', $order), ['note' => str_repeat('x', 1001)])->assertSessionHasErrors('note');
        $this->post(route('orders.note', $order), ['note' => '   '])->assertSessionHas('success');
        $this->assertNull($order->fresh()->note);                                            // xóa ghi chú

        $this->post(route('orders.note', $order), ['note' => 'Ghi chú cuối']);
        app(OrderService::class)->confirm($order, $this->admin());
        $this->post(route('orders.note', $order), ['note' => 'Sửa sau khi xác nhận'])->assertSessionHas('error');
        $this->assertSame('Ghi chú cuối', $order->fresh()->note);
        $this->get(route('orders.show', $order))->assertDontSee('id="order-note"', false)->assertSee('Ghi chú cuối');

        // Shop thấy ghi chú nổi bật ở trang đơn
        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertSee('Ghi chú của khách:')->assertSee('Ghi chú cuối');
    }

    public function test_note_is_escaped_everywhere(): void
    {
        $order = $this->place();
        app(OrderService::class)->updateNote($order, $this->customer(), '<script>alert(1)</script>');
        foreach ([route('orders.invoice', $order), route('orders.show', $order)] as $url) {
            $this->actingAs($this->customer())->get($url)->assertDontSee('<script>alert(1)', false)->assertSee('&lt;script&gt;', false);
        }
        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertDontSee('<script>alert(1)', false);
    }
}

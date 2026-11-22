<?php

namespace Tests\Feature\Services;

use App\Enums\ActorType;
use App\Enums\CommentStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\BusinessException;
use App\Models\Article;
use App\Models\Order;
use App\Models\Plant;
use App\Models\PlantReview;
use App\Models\User;
use App\Services\CommentService;
use App\Services\OrderService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** H4: đánh giá (chỉ người đã nhận cây, hiện ngay, shop gỡ được, điểm luôn khớp) + hỏi đáp. */
class ReviewAndCommentTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private ReviewService $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviews = app(ReviewService::class);
    }

    private function delivered(?User $user = null, array $cart = [1 => 1]): Order
    {
        $orders = app(OrderService::class);
        $o = $this->place($user, $cart);
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'R'.$o->id, $this->admin());

        return $orders->complete($o, $this->admin());
    }

    private function plant(): Plant
    {
        return Plant::find(1);
    }

    public function test_only_customers_who_received_the_plant_can_review(): void
    {
        try {
            $this->reviews->submit($this->customer(), 1, 5, null, 'Đẹp');
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('mua và nhận', $e->getMessage());
        }
        $this->place();                                                        // đặt nhưng chưa nhận: vẫn chưa được
        $this->expectException(BusinessException::class);
        $this->reviews->submit($this->customer(), 1, 5, null, 'Đẹp');
    }

    public function test_returned_order_is_not_proof_of_purchase(): void
    {
        $o = $this->delivered();
        app(OrderService::class)->markReturning($o, ActorType::Customer, $this->customer(), 'Héo');
        $this->assertNull($this->reviews->proofOfPurchase($this->customer(), 1));
    }

    public function test_rating_totals_follow_moderation_edit_and_delete(): void
    {
        $this->delivered();
        $r = $this->reviews->submit($this->customer(), 1, 4, 'Ổn', 'Cây khỏe');      // hiện ngay, tính điểm luôn
        $this->assertSame([ReviewStatus::Approved, 4, 1], [$r->status, $this->plant()->rating_sum, $this->plant()->rating_count]);
        $this->assertNull($r->moderated_by);

        $this->reviews->submit($this->customer(), 1, 2, 'Sửa', 'Lá vàng');      // sửa -> vẫn hiện, thay điểm cũ bằng điểm mới
        $this->assertSame([ReviewStatus::Approved, 2, 1], [$r->refresh()->status, $this->plant()->rating_sum, $this->plant()->rating_count]);
        $this->assertSame(1, PlantReview::count());

        try {
            $this->reviews->moderate($r, ReviewStatus::Rejected, $this->admin());
            $this->fail('Từ chối phải có lý do');
        } catch (BusinessException) {
        }
        $this->reviews->moderate($r, ReviewStatus::Rejected, $this->admin(), 'Ngôn từ không phù hợp');   // shop gỡ
        $this->assertSame([0, 0], [$this->plant()->rating_sum, $this->plant()->rating_count]);

        // Bị gỡ rồi khách sửa lại: KHÔNG tự hiện lại, chờ shop duyệt
        $this->reviews->submit($this->customer(), 1, 3, 'Viết lại', 'Lời lẽ ôn hòa');
        $this->assertSame([ReviewStatus::Pending, 0, 0], [$r->refresh()->status, $this->plant()->rating_sum, $this->plant()->rating_count]);
        $this->assertNull($r->reject_reason);

        $this->reviews->moderate($r, ReviewStatus::Approved, $this->admin());
        $this->assertSame([3, 1, 3.0], [$this->plant()->rating_sum, $this->plant()->rating_count, $this->plant()->rating_avg]);
        $this->reviews->delete($r->refresh());
        $this->assertSame([0, 0], [$this->plant()->rating_sum, $this->plant()->rating_count]);
        $this->assertSoftDeleted($r);

        $again = $this->reviews->submit($this->customer(), 1, 5, null, 'Viết lại');   // khôi phục bản đã xóa, không tạo trùng
        $this->assertTrue($again->is($r));
        $this->assertSame([ReviewStatus::Approved, 5, 1], [$again->status, $this->plant()->rating_sum, $this->plant()->rating_count]);
        $this->artisan('shop:reconcile')->assertSuccessful();
    }

    /** Khách tự xóa đánh giá đã bị shop gỡ rồi viết lại: vẫn phải chờ duyệt (xóa không "rửa" được trạng thái bị gỡ). */
    public function test_deleting_a_rejected_review_does_not_bypass_moderation(): void
    {
        $this->delivered();
        $r = $this->reviews->submit($this->customer(), 1, 1, null, 'Spam');
        $this->reviews->moderate($r, ReviewStatus::Rejected, $this->admin(), 'Spam');
        $this->reviews->delete($r->refresh());
        $again = $this->reviews->submit($this->customer(), 1, 1, null, 'Spam lần nữa');
        $this->assertSame(ReviewStatus::Pending, $again->status);
        $this->assertSame([0, 0], [$this->plant()->rating_sum, $this->plant()->rating_count]);
        $this->artisan('shop:reconcile')->assertSuccessful();
    }

    public function test_helpful_votes(): void
    {
        $this->delivered();
        $r = $this->reviews->submit($this->customer(), 1, 5, null, 'Tốt');
        $voter = User::factory()->create();
        $this->reviews->moderate($r, ReviewStatus::Rejected, $this->admin(), 'Kiểm tra');
        try {
            $this->reviews->toggleHelpful($r, $voter);
            $this->fail('Đánh giá đang bị gỡ thì không bình chọn được');
        } catch (BusinessException) {
        }
        $this->reviews->moderate($r, ReviewStatus::Approved, $this->admin());
        $this->assertTrue($this->reviews->toggleHelpful($r, $voter));
        $this->assertSame(1, $r->refresh()->helpful_count);
        $this->assertFalse($this->reviews->toggleHelpful($r, $voter));          // bấm lần 2 = bỏ
        $this->assertSame(0, $r->refresh()->helpful_count);
        $this->expectExceptionMessage('chính bạn');
        $this->reviews->toggleHelpful($r, $this->customer());
    }

    public function test_questions_replies_are_one_level_and_shop_replies_are_pinned(): void
    {
        $svc = app(CommentService::class);
        $q = $svc->post($this->customer(), $this->plant(), 'Cây để phòng máy lạnh được không?');
        $a = $svc->post(User::factory()->create(), $this->plant(), 'Được bạn ơi', $q);
        $nested = $svc->post($this->customer(), $this->plant(), 'Cảm ơn', $a);                // trả lời của trả lời
        $this->assertSame($q->id, $nested->parent_id);                                          // gắn về câu hỏi gốc
        $shop = $svc->post($this->admin(), $this->plant(), 'Được, tránh gió thổi trực tiếp.', $q);
        $this->assertTrue($shop->is_pinned);
        $this->assertFalse($a->is_pinned);

        try {
            $svc->post($this->customer(), Article::first(), 'Sai chủ đề', $q);
            $this->fail();
        } catch (BusinessException $e) {
            $this->assertStringContainsString('không thuộc', $e->getMessage());
        }
        $svc->setStatus($q, CommentStatus::Hidden);
        try {
            $svc->post($this->customer(), $this->plant(), 'Trả lời câu đã ẩn', $q->refresh());
            $this->fail();
        } catch (BusinessException) {
        }
        $this->expectException(BusinessException::class);
        $svc->delete($q, User::factory()->create());                                            // không xóa của người khác
    }
}

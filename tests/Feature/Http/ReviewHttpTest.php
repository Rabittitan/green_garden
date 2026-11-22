<?php

namespace Tests\Feature\Http;

use App\Enums\ReviewStatus;
use App\Models\Comment;
use App\Models\PlantReview;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

class ReviewHttpTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    public function test_review_and_qa_flow_over_http(): void
    {
        $this->actingAs($this->customer())->get(route('plants.show', 1))->assertOk()->assertSee('Chỉ khách đã mua và nhận cây');

        $orders = app(OrderService::class);
        $o = $this->place();
        $orders->confirm($o, $this->admin());
        $orders->ship($o, 'GHN', 'H1', $this->admin());
        $orders->complete($o, $this->admin());

        $this->actingAs($this->customer())->get(route('plants.show', 1))->assertSee('Gửi đánh giá');
        $this->post(route('reviews.store', 1), ['rating' => 9])->assertSessionHasErrors('rating');
        $this->post(route('reviews.store', 1), ['rating' => 5, 'title' => 'Tuyệt', 'content' => 'Cây xanh tốt'])
            ->assertSessionHas('success', 'Cảm ơn bạn! Đánh giá đã được đăng.');
        $review = PlantReview::sole();
        $this->assertSame(ReviewStatus::Approved, $review->status);                        // không chờ duyệt
        $this->get(route('plants.show', 1))->assertSee('Đã hiển thị')->assertSee('Cây xanh tốt</div>', false);

        $staff = User::factory()->staff()->create();
        // Không có đánh giá chờ duyệt -> trang kiểm duyệt mở sẵn tab đánh giá đang hiển thị
        $this->actingAs($staff)->get(route('admin.reviews.index'))->assertOk()->assertSee('Cây xanh tốt');
        $this->post(route('admin.reviews.moderate', $review), ['status' => 'rejected', 'reason' => 'Thử gỡ'])->assertSessionHas('success');
        $this->actingAs($this->customer())->get(route('plants.show', 1))->assertSee('Bị từ chối')->assertDontSee('Cây xanh tốt</div>', false);
        $this->actingAs($staff)->post(route('admin.reviews.moderate', $review), ['status' => 'approved'])->assertSessionHas('success');
        $this->post(route('admin.reviews.reply', $review), ['shop_reply' => 'Cảm ơn bạn!'])->assertSessionHas('success');
        $this->assertSame(ReviewStatus::Approved, $review->refresh()->status);

        $voter = User::factory()->create();
        $this->actingAs($voter)->get(route('plants.show', 1))->assertSee('Cây xanh tốt')->assertSee('Shop phản hồi')->assertSee('5</strong>/5', false);
        $this->post(route('reviews.vote', $review))->assertSessionHas('success');
        $this->assertSame(1, $review->refresh()->helpful_count);

        $this->post(route('comments.plant', 1), ['content' => 'Bao lâu tưới 1 lần?'])->assertSessionHas('success');
        $q = Comment::sole();
        $this->actingAs($staff)->get(route('admin.comments.index'))->assertOk()->assertSee('Bao lâu tưới');
        $this->post(route('admin.comments.reply', $q), ['content' => '1 tuần 1 lần.'])->assertSessionHas('success');
        $this->get(route('admin.comments.index'))->assertDontSee('Bao lâu tưới');            // đã trả lời -> rời danh sách chờ
        $this->get(route('plants.show', 1))->assertSee('Shop trả lời')->assertSee('1 tuần 1 lần.');

        $this->post(route('admin.comments.toggle', $q));
        $this->get(route('plants.show', 1))->assertDontSee('Bao lâu tưới');

        $this->actingAs($this->customer())->delete(route('reviews.destroy', $review))->assertSessionHas('success');
        $this->actingAs($this->customer())->delete(route('comments.destroy', $q))->assertForbidden();   // không phải chủ câu hỏi
        $this->actingAs($voter)->delete(route('comments.destroy', $q))->assertSessionHas('success');
        $this->artisan('shop:reconcile')->assertSuccessful();
    }

    public function test_article_comments_and_guest_view(): void
    {
        $this->get(route('news.show', 1))->assertOk()->assertSee('Đăng nhập');
        $this->post(route('comments.article', 1), ['content' => 'Hay quá'])->assertRedirect(route('login'));
        $this->actingAs($this->customer())->post(route('comments.article', 1), ['content' => 'Bài hay quá'])->assertSessionHas('success');
        $this->get(route('news.show', 1))->assertSee('Bài hay quá');
    }
}

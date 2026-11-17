<?php

namespace Tests\Feature\Models;

use App\Enums\ActorType;
use App\Enums\CommentStatus;
use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RedemptionStatus;
use App\Enums\ReviewStatus;
use App\Enums\StockMovementType;
use App\Enums\TierChangeReason;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Enum PHP và danh sách giá trị trong CHECK của DB phải khớp tuyệt đối (không thiếu, không thừa). */
class EnumsMatchDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public static function pairs(): array
    {
        return [
            [OrderStatus::class, 'chk_o_status'],
            [PaymentStatus::class, 'chk_o_ps'],
            [PaymentMethod::class, 'chk_o_pm'],
            [PaymentMethod::class, 'chk_pay_method'],
            [PaymentType::class, 'chk_pay_type'],
            [PaymentState::class, 'chk_pay_status'],
            [StockMovementType::class, 'chk_sm_type'],
            [TierChangeReason::class, 'chk_mth_reason'],
            [UserRole::class, 'chk_users_role'],
            [ActorType::class, 'chk_osh_actor'],
            [RedemptionStatus::class, 'chk_cr_status'],
            [CouponType::class, 'chk_coupon_type'],
            [CouponScope::class, 'chk_coupon_scope'],
            [GoodsReceiptStatus::class, 'chk_gr_status'],
            [PurchaseRequestStatus::class, 'chk_pr_status'],
            [ReviewStatus::class, 'chk_plant_reviews_status'],
            [CommentStatus::class, 'chk_comments_status'],
        ];
    }

    /** @param class-string<\BackedEnum> $enum */
    #[DataProvider('pairs')]
    public function test_enum_values_equal_check_constraint_values(string $enum, string $constraint): void
    {
        $clause = DB::table('information_schema.CHECK_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('CONSTRAINT_NAME', $constraint)
            ->value('CHECK_CLAUSE');
        $this->assertNotNull($clause, "Không thấy CHECK {$constraint}");

        // MySQL lưu dạng _utf8mb4\'pending\' (có dấu \ trước nháy)
        preg_match_all("/'([a-z_]+)\\\\?'/", $clause, $m);
        $dbValues = array_values(array_unique($m[1]));
        $enumValues = array_map(fn ($c) => $c->value, $enum::cases());
        sort($dbValues);
        sort($enumValues);

        $this->assertSame($dbValues, $enumValues, "{$enum} lệch với {$constraint}");
    }
}

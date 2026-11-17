<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TierChangeReason;
use App\Exceptions\BusinessException;
use App\Models\MembershipTier;
use App\Models\MembershipTierHistory;
use App\Models\Order;
use App\Models\User;
use App\Notifications\TierUpgraded;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Hạng thành viên CỐ ĐỊNH theo tổng chi tiêu trọn đời.
 *
 *  lifetime_spend = Σ max(0, paid_amount − refunded_amount − (shipping_fee − shipping_discount))
 *                   trên các đơn đã CHỐT chi tiêu (spend_recorded_at NOT NULL).
 *  Đơn được chốt khi đã hoàn thành VÀ qua hạn đổi trả -> không thể "mua để lên hạng rồi trả hàng".
 *  Hạng chỉ tự động đi LÊN; chỉ admin hạ được (có lịch sử, có người chịu trách nhiệm).
 *
 * Thứ tự khóa: users -> orders (giống đặt hàng). Mọi hàm chạy transaction RIÊNG,
 * không gọi lồng trong transaction của đơn hàng.
 */
class MembershipService
{
    /** Chốt chi tiêu cho các đơn đã qua hạn đổi trả. Trả số đơn đã chốt. */
    public function recordDueSpend(int $limit = 200): int
    {
        $due = Order::status(OrderStatus::Completed)
            ->whereNull('spend_recorded_at')
            ->where('completed_at', '<=', now()->subDays((int) config('shop.return_window_days')))
            ->orderBy('id')->limit($limit)->get(['id', 'user_id']);

        $count = 0;
        foreach ($due as $row) {
            $count += DB::transaction(function () use ($row) {
                $user = User::whereKey($row->user_id)->lockForUpdate()->firstOrFail();
                $order = Order::whereKey($row->id)->lockForUpdate()->firstOrFail();
                if ($order->status !== OrderStatus::Completed || $order->spend_recorded_at !== null || $order->isWithinReturnWindow()) {
                    return 0;                                               // đã bị xử lý / trả hàng trong lúc chờ
                }
                $order->update(['spend_recorded_at' => now()]);
                $this->recalculate($user, $order);

                return 1;
            }, 3);
        }

        return $count;
    }

    /** Tính lại tổng chi tiêu (vd sau khi hoàn tiền một đơn đã chốt). Không bao giờ hạ hạng. */
    public function refreshUser(int $userId): User
    {
        return DB::transaction(function () use ($userId) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $this->recalculate($user, null);

            return $user;
        }, 3);
    }

    /** Admin đổi hạng tay (lên hoặc xuống), bắt buộc ghi lý do. */
    public function setManual(User $user, MembershipTier $tier, User $by, string $note): User
    {
        if (trim($note) === '') {
            throw new BusinessException('Phải ghi lý do khi đổi hạng thủ công.');
        }

        return DB::transaction(function () use ($user, $tier, $by, $note) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->membership_tier_id === $tier->id) {
                return $user;
            }
            $this->changeTier($user, $tier, TierChangeReason::Manual, null, $by->id, $note);

            return $user;
        }, 3);
    }

    public function spendOf(int $userId): int
    {
        // CAST sang SIGNED: trên cột UNSIGNED, phép trừ ra số âm sẽ làm MySQL báo lỗi tràn số
        return (int) Order::where('user_id', $userId)->whereNotNull('spend_recorded_at')
            ->sum(DB::raw('GREATEST(0, CAST(paid_amount AS SIGNED) - CAST(refunded_amount AS SIGNED)'
                .' - (CAST(shipping_fee AS SIGNED) - CAST(shipping_discount AS SIGNED)))'));
    }

    /** User phải đang bị khóa dòng. */
    private function recalculate(User $user, ?Order $trigger): void
    {
        $spend = $this->spendOf($user->id);
        $user->forceFill(['lifetime_spend' => $spend])->save();

        $target = MembershipTier::forSpend($spend);
        if (! $target->isHigherThan($user->tier)) {
            return;
        }

        // Tôn trọng lần đổi hạng thủ công gần nhất: chỉ tự nâng khi chi tiêu VƯỢT sang ngưỡng cao hơn
        // ngưỡng khách đã đạt tại thời điểm admin đổi tay (vd hạ hạng do gian lận không bị tự nâng lại ngay).
        $manual = MembershipTierHistory::where('user_id', $user->id)
            ->where('reason', TierChangeReason::Manual->value)->latest('id')->first();
        if ($manual && ! $target->isHigherThan(MembershipTier::forSpend($manual->spend_total))) {
            return;
        }

        $this->changeTier($user, $target, TierChangeReason::Upgrade, $trigger?->id, null, null);
    }

    /** Tính lại cho mọi khách (sau khi đổi ngưỡng hạng). Trả số khách được nâng hạng. */
    public function recalculateAll(): int
    {
        $upgraded = 0;
        User::where('role', 'customer')->orderBy('id')->select('id', 'membership_tier_id')
            ->chunkById(200, function ($users) use (&$upgraded) {
                foreach ($users as $u) {
                    $upgraded += $this->refreshUser($u->id)->membership_tier_id !== $u->membership_tier_id ? 1 : 0;
                }
            });

        return $upgraded;
    }

    private function changeTier(User $user, MembershipTier $to, TierChangeReason $reason, ?int $orderId, ?int $by, ?string $note): void
    {
        $from = $user->membership_tier_id;
        $user->forceFill(['membership_tier_id' => $to->id, 'tier_achieved_at' => now()])->save();
        $user->unsetRelation('tier');

        if ($to->rank > MembershipTier::findOrFail($from)->rank) {
            Notifier::send($user, new TierUpgraded($to));
        }

        MembershipTierHistory::create([
            'user_id' => $user->id, 'from_tier_id' => $from, 'to_tier_id' => $to->id, 'reason' => $reason,
            'spend_total' => $user->lifetime_spend, 'order_id' => $orderId, 'changed_by' => $by, 'note' => $note,
        ]);
    }
}

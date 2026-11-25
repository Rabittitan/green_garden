<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\MembershipTier;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Khách hàng: hạng, tổng chi tiêu, lịch sử hạng, đơn hàng; admin đổi hạng tay (có lý do). */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = $this->withPurchaseTotals(User::where('role', UserRole::Customer->value)->with('tier'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('email', 'like', '%'.$request->q.'%')->orWhere('phone', 'like', '%'.$request->q.'%')))
            ->when($request->filled('tier'), fn ($q) => $q->where('membership_tier_id', $request->integer('tier')))
            ->orderByDesc('completed_paid_sum')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.customers.index', ['customers' => $customers, 'tiers' => MembershipTier::ordered()->get()]);
    }

    public function show(User $user): View
    {
        $user->load(['tier', 'addresses', 'tierHistories.fromTier', 'tierHistories.toTier', 'tierHistories.changedBy']);
        $totals = $this->withPurchaseTotals(User::whereKey($user->id))->firstOrFail();
        foreach (['orders_count', 'completed_orders_count', 'completed_paid_sum', 'completed_refunded_sum'] as $k) {
            $user->setAttribute($k, $totals->getAttribute($k));
        }

        return view('admin.customers.show', [
            'customer' => $user,
            'orders' => $user->orders()->latest('id')->paginate(10),
            'tiers' => MembershipTier::ordered()->get(),
        ]);
    }

    /**
     * Số đơn + "đã mua" (tiền thực trả của đơn HOÀN THÀNH, trừ tiền đã hoàn, gồm phí ship) tính ngay từ đơn hàng.
     * Khác users.lifetime_spend: đó là chi tiêu XÉT HẠNG, chỉ cộng sau 7 ngày đổi trả và không gồm phí ship.
     *
     * @param  Builder<User>  $q
     * @return Builder<User>
     */
    private function withPurchaseTotals(Builder $q): Builder
    {
        $completed = fn ($o) => $o->where('status', OrderStatus::Completed->value);

        return $q->withCount(['orders', 'orders as completed_orders_count' => $completed])
            ->withSum(['orders as completed_paid_sum' => $completed], 'paid_amount')
            ->withSum(['orders as completed_refunded_sum' => $completed], 'refunded_amount');
    }

    public function setTier(Request $request, User $user, MembershipService $membership): RedirectResponse
    {
        $data = $request->validate(['tier_id' => ['required', 'exists:membership_tiers,id'], 'note' => ['required', 'string', 'max:255']],
            [], ['note' => 'lý do']);
        try {
            $membership->setManual($user, MembershipTier::findOrFail($data['tier_id']), $request->user(), $data['note']);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã đổi hạng thành viên.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        abort_if($user->isBackOffice(), 403);
        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('success', $user->is_active ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.');
    }
}

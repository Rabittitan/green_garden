<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\MembershipTier;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\Request;

/** Khách hàng: hạng, tổng chi tiêu, lịch sử hạng, đơn hàng; admin đổi hạng tay (có lý do). */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = User::where('role', 'customer')->with('tier')->withCount('orders')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('email', 'like', '%'.$request->q.'%')->orWhere('phone', 'like', '%'.$request->q.'%')))
            ->when($request->filled('tier'), fn ($q) => $q->where('membership_tier_id', $request->integer('tier')))
            ->orderByDesc('lifetime_spend')->paginate(20)->withQueryString();

        return view('admin.customers.index', ['customers' => $customers, 'tiers' => MembershipTier::ordered()->get()]);
    }

    public function show(User $user)
    {
        $user->load(['tier', 'addresses', 'tierHistories.fromTier', 'tierHistories.toTier', 'tierHistories.changedBy']);

        return view('admin.customers.show', [
            'customer' => $user,
            'orders' => $user->orders()->latest('id')->paginate(10),
            'tiers' => MembershipTier::ordered()->get(),
        ]);
    }

    public function setTier(Request $request, User $user, MembershipService $membership)
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

    public function toggleActive(User $user)
    {
        abort_if($user->isBackOffice(), 403);
        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('success', $user->is_active ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.');
    }
}

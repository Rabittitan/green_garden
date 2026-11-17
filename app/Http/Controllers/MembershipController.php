<?php

namespace App\Http\Controllers;

use App\Models\MembershipTier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Trang "Hạng thành viên của tôi". */
class MembershipController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('tier');
        $next = $user->tier->next();
        $pendingSpend = $user->orders()->where('status', 'completed')->whereNull('spend_recorded_at')->get()->sum('spend_contribution');

        return view('account.membership', [
            'user' => $user,
            'next' => $next,
            'remaining' => $next ? max(0, $next->min_spend - $user->lifetime_spend) : 0,
            'progress' => $next ? min(100, (int) floor($user->lifetime_spend * 100 / max(1, $next->min_spend))) : 100,
            'pendingSpend' => $pendingSpend,
            'tiers' => MembershipTier::active()->ordered()->get(),
            'histories' => $user->tierHistories()->with(['fromTier', 'toTier'])->get(),
            'returnDays' => (int) config('shop.return_window_days'),
        ]);
    }
}

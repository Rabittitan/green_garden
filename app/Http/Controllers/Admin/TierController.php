<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\MembershipTier;
use App\Services\MembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cấu hình hạng: ngưỡng chi tiêu, % giảm, trần giảm, ngưỡng miễn ship.
 * code / rank cố định. Ngưỡng phải tăng dần theo rank; hạng cơ bản luôn = 0.
 * Đổi ngưỡng KHÔNG hạ hạng ai (hạng chỉ đi lên); khách đủ ngưỡng mới được nâng ngay sau khi lưu.
 */
class TierController extends Controller
{
    public function index(): View
    {
        return view('admin.tiers.index', ['tiers' => MembershipTier::ordered()->withCount('users')->get()]);
    }

    public function update(Request $request, MembershipService $membership): RedirectResponse
    {
        $tiers = MembershipTier::ordered()->get();
        $rules = [];
        foreach ($tiers as $t) {
            $rules["tiers.{$t->id}.name"] = ['required', 'string', 'max:50'];
            $rules["tiers.{$t->id}.min_spend"] = $t->rank === 0 ? ['required', 'integer', 'in:0'] : ['required', 'integer', 'min:1'];
            $rules["tiers.{$t->id}.discount_percent"] = ['required', 'integer', 'min:0', 'max:50'];
            $rules["tiers.{$t->id}.max_discount_per_order"] = ['nullable', 'integer', 'min:1'];
            $rules["tiers.{$t->id}.free_shipping_min_order"] = ['nullable', 'integer', 'min:0'];
        }
        $data = $request->validate($rules)['tiers'];
        try {
            $upgraded = $membership->saveTierConfig($data);
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã lưu cấu hình hạng thành viên.'.($upgraded ? " {$upgraded} khách được nâng hạng." : ''));
    }
}

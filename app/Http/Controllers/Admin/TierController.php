<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipTier;
use App\Services\MembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function update(Request $request): RedirectResponse
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

        $prev = -1;
        foreach ($tiers as $t) {
            if ((int) $data[$t->id]['min_spend'] <= $prev) {
                return back()->withInput()->with('error', "Ngưỡng của hạng {$t->name} phải lớn hơn hạng thấp hơn.");
            }
            $prev = (int) $data[$t->id]['min_spend'];
        }

        DB::transaction(function () use ($tiers, $data) {
            // min_spend UNIQUE: tạm đẩy về số âm-ảo (lớn) trước để hoán đổi ngưỡng không đụng nhau
            foreach ($tiers as $t) {
                if ($t->rank > 0) {
                    $t->update(['min_spend' => 9_000_000_000_000 + $t->rank]);
                }
            }
            foreach ($tiers as $t) {
                $t->update($data[$t->id]);
            }
        });

        // Ngưỡng thấp đi -> khách đủ điều kiện được nâng ngay (hạng không bao giờ tự hạ)
        $upgraded = app(MembershipService::class)->recalculateAll();

        return back()->with('success', 'Đã lưu cấu hình hạng thành viên.'.($upgraded ? " {$upgraded} khách được nâng hạng." : ''));
    }
}

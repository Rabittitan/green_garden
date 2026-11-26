<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** "Mã của tôi": mã riêng được tặng (còn dùng / đã dùng / hết hạn) + mã chung đang mở cho mọi khách. Chỉ đọc. */
class CouponWalletController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $usable = Coupon::walletUsable($userId)->orderBy('ends_at')->get();
        $history = Coupon::where('owner_user_id', $userId)->whereNotIn('id', $usable->pluck('id'))->latest('id')->take(20)->get();
        // Mã chung đang áp dụng (không giới hạn khách cụ thể); điều kiện chi tiết (hạng, đơn đầu, giá trị tối thiểu) xét lúc áp mã
        $public = Coupon::whereNull('owner_user_id')->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($w) => $w->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->with('minTier')->orderBy('ends_at')->take(20)->get();

        return view('account.coupons', compact('usable', 'history', 'public'));
    }
}

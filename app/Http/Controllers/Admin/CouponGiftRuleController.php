<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\CouponGiftRule;
use App\Models\User;
use App\Services\CouponGiftService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Quà tặng khi mua (chỉ quản trị viên): luật tặng mã tự động + tặng tay 1 mã cho 1 khách. */
class CouponGiftRuleController extends Controller
{
    public function index(): View
    {
        return view('admin.coupon-gifts.index', ['rules' => CouponGiftRule::withCount('coupons')->latest('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.coupon-gifts.create', ['rule' => new CouponGiftRule(['type' => 'fixed', 'valid_days' => 30, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = new CouponGiftRule($this->validated($request, true));
        $rule->created_by = $request->user()->id;
        $rule->save();

        return redirect()->route('admin.coupon-gifts.index')->with('success', 'Đã tạo luật tặng mã. Chỉ áp cho đơn hoàn thành từ bây giờ.');
    }

    public function edit(CouponGiftRule $rule): View
    {
        return view('admin.coupon-gifts.edit', compact('rule'));
    }

    public function update(Request $request, CouponGiftRule $rule): RedirectResponse
    {
        $rule->update($this->validated($request, true));

        return redirect()->route('admin.coupon-gifts.index')->with('success', 'Đã cập nhật luật tặng mã (mã đã tặng trước đó giữ nguyên).');
    }

    /** Tặng tay 1 mã cho 1 khách (từ trang chi tiết khách hàng). */
    public function giftToCustomer(Request $request, User $user, CouponGiftService $gifts): RedirectResponse
    {
        $data = $this->validated($request, false);
        try {
            $coupon = $gifts->giftManually($user, $data, $request->user());
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã tặng mã {$coupon->code} cho {$user->name} (đã gửi email báo khách).");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $isRule): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['nullable', 'integer', 'min:0'],
            'max_discount_amount' => ['nullable', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'valid_days' => ['required', 'integer', 'between:1,365'],
        ];
        if ($isRule) {
            $rules['min_order_total'] = ['required', 'integer', 'min:0'];
        }
        $v = validator($request->all(), $rules, [], ['name' => 'tên', 'type' => 'loại', 'value' => 'giá trị', 'valid_days' => 'số ngày hạn dùng',
            'min_order_total' => 'tổng đơn tối thiểu', 'min_order_amount' => 'đơn tối thiểu khi dùng mã', 'max_discount_amount' => 'giảm tối đa']);
        $v->after(function (Validator $v) use ($request) {
            $value = (int) $request->input('value');
            match ($request->input('type')) {
                'percent' => ($value < 1 || $value > 100) && $v->errors()->add('value', 'Giảm theo % phải từ 1 đến 100.'),
                'fixed' => $value < 1 && $v->errors()->add('value', 'Số tiền giảm phải lớn hơn 0.'),
                default => null,
            };
        });
        $data = $v->validate();
        $data['value'] = $data['type'] === 'free_shipping' ? 0 : (int) ($data['value'] ?? 0);
        $data['min_order_amount'] = (int) ($data['min_order_amount'] ?? 0);
        $data['max_discount_amount'] = $data['type'] === 'percent' ? ($data['max_discount_amount'] ?? null) : null;
        if ($isRule) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\MembershipTier;
use App\Models\Plant;
use App\Services\CouponService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Mã giảm giá. Mã đã có người dùng thì KHÓA code / loại / giá trị / phạm vi (chỉ đổi thời hạn, lượt, bật tắt). */
class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::withTrashed()->with('minTier')->latest('id')->paginate(20);

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        return view('admin.coupons.create', $this->formData(new Coupon(['type' => 'percent', 'scope' => 'order', 'usage_limit_per_user' => 1, 'combinable_with_tier' => true, 'is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        DB::transaction(function () use ($data, $request) {
            $coupon = Coupon::create($data['attrs'] + ['created_by' => $request->user()->id]);
            $coupon->plants()->sync($data['plant_ids']);
            $coupon->categories()->sync($data['category_ids']);
        });

        return redirect()->route('admin.coupons.index')->with('success', 'Đã tạo mã giảm giá.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.edit', $this->formData($coupon->load(['plants', 'categories'])));
    }

    public function update(Request $request, Coupon $coupon, CouponService $coupons): RedirectResponse
    {
        $data = $this->validated($request, $coupon);
        try {
            $coupons->update($coupon, $data['attrs'], $data['plant_ids'], $data['category_ids']);
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.coupons.index')->with('success', 'Đã cập nhật mã giảm giá.');
    }

    /** Xóa mềm: đơn cũ vẫn tham chiếu được; code không được tái sử dụng. */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => false]);
        $coupon->delete();

        return back()->with('success', "Đã xóa mã {$coupon->code}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Coupon $coupon): array
    {
        return [
            'coupon' => $coupon,
            'tiers' => MembershipTier::ordered()->get(),
            'plants' => Plant::active()->orderBy('name')->get(['id', 'name', 'sku']),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
        ];
    }

    /**
     * @return array{attrs: array<string, mixed>, plant_ids: array<int, int>, category_ids: array<int, int>}
     */
    private function validated(Request $request, ?Coupon $coupon): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'integer', 'min:0'],
            'max_discount_amount' => ['nullable', 'integer', 'min:1'],
            'min_order_amount' => ['required', 'integer', 'min:0'],
            'scope' => ['required', Rule::enum(CouponScope::class)],
            'min_tier_id' => ['nullable', 'exists:membership_tiers,id'],
            'usage_limit' => ['nullable', 'integer', 'min:'.max(1, $coupon->used_count ?? 1)],
            'usage_limit_per_user' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'plant_ids' => ['array'], 'plant_ids.*' => ['integer', 'exists:plants,id'],
            'category_ids' => ['array'], 'category_ids.*' => ['integer', 'exists:categories,id'],
        ], [
            'usage_limit.min' => 'Tổng lượt không được nhỏ hơn số lượt đã dùng.',
        ], ['code' => 'mã', 'value' => 'giá trị', 'ends_at' => 'ngày kết thúc']);

        // Ràng buộc chéo (khớp CHECK trong DB, báo lỗi đẹp thay vì lỗi SQL)
        $v = validator($data, []);
        $locked = $coupon?->isLocked() ?? false;
        $v->after(function (Validator $v) use ($data, $locked) {
            match ($data['type']) {
                'percent' => ($data['value'] < 1 || $data['value'] > 100) && $v->errors()->add('value', 'Giảm theo % phải từ 1 đến 100.'),
                'fixed' => $data['value'] < 1 && $v->errors()->add('value', 'Số tiền giảm phải lớn hơn 0.'),
                'free_shipping' => $data['scope'] !== 'order' && $v->errors()->add('scope', 'Mã miễn phí ship chỉ áp cho cả đơn.'),
                default => null,
            };
            if ($locked) {
                return;                                                     // phạm vi đã khóa, giữ nguyên danh sách cũ
            }
            if ($data['scope'] === 'plants' && empty($data['plant_ids'])) {
                $v->errors()->add('plant_ids', 'Chọn ít nhất 1 cây.');
            }
            if ($data['scope'] === 'categories' && empty($data['category_ids'])) {
                $v->errors()->add('category_ids', 'Chọn ít nhất 1 danh mục.');
            }
        });
        $v->validate();

        $attrs = Arr::except($data, ['plant_ids', 'category_ids']);
        $attrs['value'] = $data['type'] === 'free_shipping' ? 0 : (int) $data['value'];
        $attrs['combinable_with_tier'] = $request->boolean('combinable_with_tier');
        $attrs['first_order_only'] = $request->boolean('first_order_only');
        $attrs['is_active'] = $request->boolean('is_active');

        return [
            'attrs' => $attrs,
            'plant_ids' => $data['scope'] === 'plants' ? ($data['plant_ids'] ?? []) : [],
            'category_ids' => $data['scope'] === 'categories' ? ($data['category_ids'] ?? []) : [],
        ];
    }
}

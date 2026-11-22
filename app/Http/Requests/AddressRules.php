<?php

namespace App\Http\Requests;

use App\Rules\WardInProvince;
use App\Support\VnDivisions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quy tắc địa chỉ dùng chung cho sổ địa chỉ và form đặt hàng.
 *
 * Địa giới từ 01/07/2025 (K9): chọn Tỉnh/thành → Xã/phường/đặc khu trong danh mục VnDivisions; không còn cấp huyện.
 * Cột `district` (bắt buộc trong schema) được server ghi bằng chính tên tỉnh — khách không nhập;
 * khi hiển thị, full_address bỏ phần trùng này. Đơn/địa chỉ cũ (3 cấp) giữ nguyên dữ liệu.
 */
final class AddressRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'recipient_name' => ['required', 'string', 'max:100'],
            $prefix.'phone' => ['required', 'regex:/^(0|\+84)[0-9]{9,10}$/'],
            $prefix.'province' => ['required', 'string', 'max:100', Rule::in(VnDivisions::provinces())],
            $prefix.'district' => ['required', 'string', 'max:100'],
            $prefix.'ward' => ['required', 'string', 'max:100', new WardInProvince($prefix.'province')],
            $prefix.'address_line' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Gọi trong prepareForValidation(): cấp huyện không còn, ghi tên tỉnh vào cột district.
     *
     * @return array<string, mixed>
     */
    public static function prepare(Request $request, string $prefix = ''): array
    {
        return [$prefix.'district' => $request->input($prefix.'province')];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $prefix = ''): array
    {
        return [
            $prefix.'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0901234567).',
            $prefix.'province.in' => 'Vui lòng chọn tỉnh/thành phố trong danh sách (34 tỉnh/thành từ 01/07/2025).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(string $prefix = ''): array
    {
        return [
            $prefix.'recipient_name' => 'tên người nhận', $prefix.'phone' => 'số điện thoại', $prefix.'province' => 'tỉnh/thành phố',
            $prefix.'district' => 'quận/huyện', $prefix.'ward' => 'phường/xã', $prefix.'address_line' => 'số nhà, tên đường',
        ];
    }
}

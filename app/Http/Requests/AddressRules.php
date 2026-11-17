<?php

namespace App\Http\Requests;

/** Quy tắc địa chỉ dùng chung cho sổ địa chỉ và form đặt hàng. */
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
            $prefix.'province' => ['required', 'string', 'max:100'],
            $prefix.'district' => ['required', 'string', 'max:100'],
            $prefix.'ward' => ['required', 'string', 'max:100'],
            $prefix.'address_line' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $prefix = ''): array
    {
        return [$prefix.'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0901234567).'];
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

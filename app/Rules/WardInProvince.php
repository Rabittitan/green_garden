<?php

namespace App\Rules;

use App\Support\VnDivisions;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/** Xã/phường phải thuộc tỉnh/thành đã chọn (danh mục sau 01/07/2025). Tỉnh sai thì để lỗi của ô tỉnh báo. */
final class WardInProvince implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private string $provinceField = 'province') {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $province = Arr::get($this->data, $this->provinceField);
        if (! is_string($province) || VnDivisions::wards($province) === []) {
            return;
        }
        if (! is_string($value) || ! VnDivisions::hasWard($province, $value)) {
            $fail('Phường/xã không thuộc tỉnh/thành phố đã chọn, vui lòng chọn lại.');
        }
    }
}

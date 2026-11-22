<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Services\Orders\PlaceOrderData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(AddressRules::prepare($this));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return AddressRules::rules() + [
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::available()))],
            'checkout_token' => ['required', 'uuid'],
            'expected_total' => ['required', 'integer', 'min:0'],
            'save_address' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return AddressRules::messages() + [
            'checkout_token.*' => 'Phiên đặt hàng không hợp lệ, vui lòng tải lại trang.',
            'payment_method.in' => 'Phương thức thanh toán này hiện không khả dụng.',
        ];
    }

    public function attributes(): array
    {
        return AddressRules::attributes() + ['payment_method' => 'phương thức thanh toán'];
    }

    /** @param array<int,int> $cart */
    public function toData(array $cart, ?string $couponCode): PlaceOrderData
    {
        return new PlaceOrderData(
            cart: $cart,
            shipping: $this->safe()->only(['recipient_name', 'phone', 'province', 'district', 'ward', 'address_line']),
            paymentMethod: PaymentMethod::from($this->input('payment_method')),
            checkoutToken: (string) $this->input('checkout_token'),
            couponCode: $couponCode,
            expectedTotal: (int) $this->input('expected_total'),
            note: $this->input('note'),
            clientIp: (string) $this->ip(),
        );
    }
}

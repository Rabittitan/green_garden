<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function rules(): array
    {
        return AddressRules::rules() + ['is_default' => ['nullable', 'boolean']];
    }

    public function messages(): array
    {
        return AddressRules::messages();
    }

    public function attributes(): array
    {
        return AddressRules::attributes();
    }
}

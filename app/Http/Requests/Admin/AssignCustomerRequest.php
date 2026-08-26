<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reseller = $this->route('customer');

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::notIn([$reseller?->getKey()]),
                Rule::exists('customers', 'id')->where('is_reseller', 0),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'یک مشتری را از نتایج جستجو انتخاب کنید.',
            'customer_id.integer' => 'یک مشتری معتبر را از نتایج جستجو انتخاب کنید.',
            'customer_id.exists' => 'مشتری انتخاب‌شده معتبر نیست.',
            'customer_id.not_in' => 'فروشنده نمی‌تواند به خودش اختصاص داده شود.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone', Rule::unique('customers', 'email')->ignore($customer)],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email', Rule::unique('customers', 'phone')->ignore($customer)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'suspension_reason' => ['nullable', 'string', 'max:255'],
            'email_verified' => ['nullable', 'boolean'],
        ];
    }
}

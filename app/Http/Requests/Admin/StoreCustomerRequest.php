<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email', 'unique:customers,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'suspension_reason' => ['nullable', 'string', 'max:255'],
            'email_verified' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreResellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'commission_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'payout_method' => ['required', 'string', 'in:auto_credit,withdrawable'],
        ];
    }
}

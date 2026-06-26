<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commission_pct' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:100'],
            'payout_method' => ['sometimes', 'nullable', 'string', Rule::in(['auto_credit', 'withdrawable'])],
        ];
    }
}

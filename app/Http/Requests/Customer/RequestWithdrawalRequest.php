<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RequestWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customer = $this->user('customer');

        return [
            'amount' => [
                'required',
                'integer',
                'min:10000',
                'max:'.$customer->reseller_earnings_balance,
            ],
        ];
    }
}

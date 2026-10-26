<?php

namespace App\Http\Requests\Customer;

use App\Services\Domains\DomainWorkspaceService;
use Illuminate\Foundation\Http\FormRequest;

class CheckDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->user('customer');
        if (! $customer || ! $customer->canPurchaseDomains()) {
            return false;
        }
        $workspaces = app(DomainWorkspaceService::class);

        return $workspaces->canPurchase($workspaces->active($this), $customer);
    }

    protected function prepareForValidation(): void
    {
        $domain = $this->input('domain');
        if (! is_string($domain)) {
            return;
        }

        $domain = mb_strtolower(trim($domain));
        if (preg_match('~^https?://~i', $domain)) {
            $domain = (string) parse_url($domain, PHP_URL_HOST);
        }
        $domain = preg_replace('/^www\.(?=[^.]+\.)/', '', rtrim($domain, '.'));
        if ($domain !== '' && ! str_contains($domain, '.') && preg_match('/^[a-z0-9-]+$/', $domain)) {
            $domain .= '.com';
        }
        if (function_exists('idn_to_ascii')) {
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
        }
        $this->merge(['domain' => $domain]);
    }

    public function rules(): array
    {
        return [
            'domain' => [
                'bail', 'required', 'string', 'max:253',
                'regex:/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})\z/',
            ],
        ];
    }

    public function messages(): array
    {
        return ['domain.required' => 'نام دامنه را وارد کنید.', 'domain.string' => 'نام دامنه معتبر وارد کنید.', 'domain.max' => 'نام دامنه بیش از حد طولانی است.', 'domain.regex' => 'نام دامنه معتبر مثل example.com وارد کنید؛ فاصله و نشانه‌های اضافی مجاز نیست.'];
    }
}

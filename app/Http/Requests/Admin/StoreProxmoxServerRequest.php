<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProxmoxServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cluster_name' => ['nullable', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'realm' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'required_without:api_token_secret', 'string'],
            'api_token_id' => ['nullable', 'required_with:api_token_secret', 'string', 'max:255'],
            'api_token_secret' => ['nullable', 'required_with:api_token_id', 'string'],
            'verify_tls' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

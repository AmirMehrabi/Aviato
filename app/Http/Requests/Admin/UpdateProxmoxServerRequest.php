<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProxmoxServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'cluster_name' => ['nullable', 'string', 'max:255'],
            'datacenter' => ['nullable', 'string', 'max:255'],
            'environment' => ['sometimes', 'required', 'string', 'max:80'],
            'host' => ['sometimes', 'required', 'string', 'max:255'],
            'port' => ['sometimes', 'required', 'integer', 'between:1,65535'],
            'realm' => ['sometimes', 'required', 'string', 'max:50'],
            'username' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'api_token_id' => ['nullable', 'required_with:api_token_secret', 'string', 'max:255'],
            'api_token_secret' => ['nullable', 'required_with:api_token_id', 'string'],
            'verify_tls' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'cpu_threshold_percent' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'ram_threshold_percent' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'disk_threshold_percent' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'api_endpoints' => ['nullable', 'string', 'max:2000'],
            'node_api_endpoints' => ['nullable', 'array'],
            'node_api_endpoints.*' => ['nullable', 'url:http,https', 'max:500'],
            'node_api_credentials' => ['nullable', 'array'],
            'node_api_credentials.*.token_id' => ['nullable', 'string', 'max:255'],
            'node_api_credentials.*.token_secret' => ['nullable', 'string', 'max:500'],
            'remove_stale_nodes' => ['nullable', 'array'],
            'remove_stale_nodes.*' => ['string', 'max:255'],
        ];
    }
}

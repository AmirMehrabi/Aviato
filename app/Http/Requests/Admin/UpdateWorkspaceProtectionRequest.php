<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceProtectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->allows('settings.manage')
            && $this->user('admin')?->allows('projects.manage');
    }

    public function rules(): array
    {
        $fields = [
            'unverified_customer_vm_limit' => ['integer', 'min:0', 'max:1000000'],
            'verified_customer_vm_limit' => ['integer', 'min:0', 'max:1000000'],
            'deleted_vm_cooldown_days' => ['integer', 'min:0', 'max:3650'],
            'wallet_retention_days' => ['integer', 'min:1', 'max:365'],
            'wallet_shutdown_percentage' => ['integer', 'min:1', 'max:100'],
            'vm_rebuild_fee_multiplier_percentage' => ['numeric', 'min:0', 'max:100'],
        ];
        $rules = ['protection' => ['required', 'array:'.implode(',', array_keys($fields))]];
        foreach ($fields as $key => $limits) {
            $rules["protection.$key"] = ['required', 'array:mode,value'];
            $rules["protection.$key.mode"] = ['required', Rule::in(['default', 'custom'])];
            $rules["protection.$key.value"] = ["exclude_unless:protection.$key.mode,custom", 'required', ...$limits];
        }

        return $rules;
    }
}

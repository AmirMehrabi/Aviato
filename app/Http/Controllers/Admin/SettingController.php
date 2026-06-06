<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'currency' => AppSetting::currency(),
            'currencies' => AppSetting::supportedCurrencies(),
            'verificationMode' => AppSetting::customerVerificationMode(),
            'verificationModes' => AppSetting::customerVerificationModes(),
            'smsGateway' => AppSetting::smsGateway(),
            'smsGateways' => AppSetting::smsGateways(),
            'sms0098Username' => (string) AppSetting::getValue(AppSetting::SMS0098_USERNAME, ''),
            'sms0098PanelNo' => (string) AppSetting::getValue(AppSetting::SMS0098_PANEL_NO, ''),
            'kavenegarTemplate' => (string) AppSetting::getValue(AppSetting::KAVENEGAR_TEMPLATE, ''),
            'vmCreationChargeEnabled' => AppSetting::vmCreationChargeEnabled(),
            'vmCreationChargePercentage' => AppSetting::vmCreationChargePercentage(),
            'unverifiedCustomerVmLimit' => AppSetting::unverifiedCustomerVmLimit(),
            'verifiedCustomerVmLimit' => AppSetting::verifiedCustomerVmLimit(),
            'deletedVmCooldownDays' => AppSetting::deletedVmCooldownDays(),
            'vmRebuildFeeMultiplierPercentage' => AppSetting::vmRebuildFeeMultiplierPercentage(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', Rule::in(array_keys(AppSetting::supportedCurrencies()))],
            'customer_verification_mode' => ['required', 'string', Rule::in(array_keys(AppSetting::customerVerificationModes()))],
            'sms_gateway' => ['required', 'string', Rule::in(array_keys(AppSetting::smsGateways()))],
            'sms0098_username' => ['nullable', 'string', 'max:255'],
            'sms0098_password' => ['nullable', 'string', 'max:255'],
            'sms0098_panel_no' => ['nullable', 'string', 'max:50'],
            'kavenegar_api_key' => ['nullable', 'string', 'max:255'],
            'kavenegar_template' => ['nullable', 'string', 'max:100'],
            'vm_creation_charge_enabled' => ['required', 'boolean'],
            'vm_creation_charge_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'unverified_customer_vm_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'verified_customer_vm_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'deleted_vm_cooldown_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'vm_rebuild_fee_multiplier_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($data['customer_verification_mode'] === 'sms') {
            $smsValidator = Validator::make($data, $this->activeSmsGatewayRules($data['sms_gateway']));
            $effectiveSecret = match ($data['sms_gateway']) {
                'sms0098' => $data['sms0098_password'] ?: (string) AppSetting::getValue(AppSetting::SMS0098_PASSWORD, ''),
                'kavenegar' => $data['kavenegar_api_key'] ?: (string) AppSetting::getValue(AppSetting::KAVENEGAR_API_KEY, ''),
            };

            if ($effectiveSecret === '') {
                $field = $data['sms_gateway'] === 'sms0098' ? 'sms0098_password' : 'kavenegar_api_key';
                $message = $data['sms_gateway'] === 'sms0098'
                    ? 'رمز عبور SMS0098 الزامی است.'
                    : 'API Key کاوه‌نگار الزامی است.';
                $smsValidator->errors()->add($field, $message);
            }

            if ($smsValidator->fails()) {
                return back()->withErrors($smsValidator)->withInput();
            }
        }

        AppSetting::setValue(AppSetting::BILLING_CURRENCY, $data['currency'], 'string', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, $data['customer_verification_mode'], 'string', 'customer');
        AppSetting::setValue(AppSetting::SMS_GATEWAY, $data['sms_gateway'], 'string', 'sms');
        AppSetting::setValue(AppSetting::SMS0098_USERNAME, $data['sms0098_username'] ?? '', 'string', 'sms0098');
        AppSetting::setValue(AppSetting::SMS0098_PANEL_NO, $data['sms0098_panel_no'] ?? '', 'string', 'sms0098');
        AppSetting::setValue(AppSetting::KAVENEGAR_TEMPLATE, $data['kavenegar_template'] ?? '', 'string', 'kavenegar');
        AppSetting::setValue(AppSetting::VM_CREATION_CHARGE_ENABLED, (bool) $data['vm_creation_charge_enabled'], 'boolean', 'billing');
        AppSetting::setValue(AppSetting::VM_CREATION_CHARGE_PERCENTAGE, (float) $data['vm_creation_charge_percentage'], 'float', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_UNVERIFIED_VM_LIMIT, (int) $data['unverified_customer_vm_limit'], 'integer', 'customer');
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFIED_VM_LIMIT, (int) $data['verified_customer_vm_limit'], 'integer', 'customer');
        AppSetting::setValue(AppSetting::CUSTOMER_DELETED_VM_COOLDOWN_DAYS, (int) $data['deleted_vm_cooldown_days'], 'integer', 'customer');
        AppSetting::setValue(AppSetting::VM_REBUILD_FEE_MULTIPLIER_PERCENTAGE, (float) $data['vm_rebuild_fee_multiplier_percentage'], 'float', 'billing');

        if (! empty($data['sms0098_password'])) {
            AppSetting::setValue(AppSetting::SMS0098_PASSWORD, $data['sms0098_password'], 'string', 'sms0098');
        }

        if (! empty($data['kavenegar_api_key'])) {
            AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, $data['kavenegar_api_key'], 'string', 'kavenegar');
        }

        return back()->with('status', 'تنظیمات ذخیره شد.');
    }

    private function activeSmsGatewayRules(string $gateway): array
    {
        return match ($gateway) {
            'sms0098' => [
                'sms0098_username' => ['required', 'string', 'max:255'],
                'sms0098_panel_no' => ['required', 'string', 'max:50'],
            ],
            'kavenegar' => [
                'kavenegar_template' => ['required', 'string', 'max:100'],
            ],
        };
    }
}

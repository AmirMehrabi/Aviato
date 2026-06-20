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
            'nationalCodeVerificationEnabled' => AppSetting::nationalCodeVerificationEnabled(),
            'nationalCodeVerificationToken' => AppSetting::nationalCodeVerificationToken(),
            'smsGateway' => AppSetting::smsGateway(),
            'smsGateways' => AppSetting::smsGateways(),
            'sms0098Username' => (string) AppSetting::getValue(AppSetting::SMS0098_USERNAME, ''),
            'sms0098PanelNo' => (string) AppSetting::getValue(AppSetting::SMS0098_PANEL_NO, ''),
            'kavenegarTemplate' => (string) AppSetting::getValue(AppSetting::KAVENEGAR_TEMPLATE, ''),
            'customerWalletNegativeThreshold' => AppSetting::customerWalletNegativeThreshold(),
            'customerWalletNegativeSmsEnabled' => AppSetting::customerWalletNegativeSmsEnabled(),
            'customerWalletNegativeSmsTemplate' => AppSetting::customerWalletNegativeSmsTemplate(),
            'smtpHost' => (string) AppSetting::getValue(AppSetting::SMTP_HOST, ''),
            'smtpPort' => (int) AppSetting::getValue(AppSetting::SMTP_PORT, 587),
            'smtpUsername' => (string) AppSetting::getValue(AppSetting::SMTP_USERNAME, ''),
            'smtpEncryption' => (string) AppSetting::getValue(AppSetting::SMTP_ENCRYPTION, 'tls'),
            'smtpEncryptions' => AppSetting::smtpEncryptions(),
            'smtpFromAddress' => (string) AppSetting::getValue(AppSetting::SMTP_FROM_ADDRESS, config('mail.from.address')),
            'smtpFromName' => (string) AppSetting::getValue(AppSetting::SMTP_FROM_NAME, config('mail.from.name')),
            'ticketEmailNotificationsEnabled' => AppSetting::ticketEmailNotificationsEnabled(),
            'ticketSmsNotificationsEnabled' => AppSetting::ticketSmsNotificationsEnabled(),
            'ticketKavenegarCustomerCreatedTemplate' => (string) AppSetting::getValue(AppSetting::TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE, ''),
            'ticketKavenegarAdminNewTemplate' => (string) AppSetting::getValue(AppSetting::TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE, ''),
            'ticketKavenegarCustomerReplyTemplate' => (string) AppSetting::getValue(AppSetting::TICKET_KAVENEGAR_CUSTOMER_REPLY_TEMPLATE, ''),
            'ticketKavenegarAdminReplyTemplate' => (string) AppSetting::getValue(AppSetting::TICKET_KAVENEGAR_ADMIN_REPLY_TEMPLATE, ''),
            'ticketKavenegarAssignmentTemplate' => (string) AppSetting::getValue(AppSetting::TICKET_KAVENEGAR_ASSIGNMENT_TEMPLATE, ''),
            'vmCreationChargeEnabled' => AppSetting::vmCreationChargeEnabled(),
            'vmCreationChargePercentage' => AppSetting::vmCreationChargePercentage(),
            'unverifiedCustomerVmLimit' => AppSetting::unverifiedCustomerVmLimit(),
            'verifiedCustomerVmLimit' => AppSetting::verifiedCustomerVmLimit(),
            'deletedVmCooldownDays' => AppSetting::deletedVmCooldownDays(),
            'vmRebuildFeeMultiplierPercentage' => AppSetting::vmRebuildFeeMultiplierPercentage(),
            'hetznerUsdToIrrRate' => AppSetting::hetznerUsdToIrrRate(),
            'hetznerPriceMarkupPercentage' => AppSetting::hetznerPriceMarkupPercentage(),
            'paymentsEnabled' => AppSetting::paymentsEnabled(),
            'defaultPaymentGateway' => AppSetting::defaultPaymentGateway(),
            'paymentGateways' => AppSetting::paymentGateways(),
            'mellatPaymentEnabled' => AppSetting::mellatPaymentEnabled(),
            'mellatPaymentMode' => AppSetting::mellatPaymentMode(),
            'mellatPaymentModes' => AppSetting::mellatPaymentModes(),
            'mellatTerminalId' => AppSetting::mellatTerminalId(),
            'mellatUsername' => AppSetting::mellatUsername(),
            'hesabroPaymentEnabled' => AppSetting::hesabroPaymentEnabled(),
            'hesabroClient' => AppSetting::hesabroClient(),
            'hesabroClientId' => AppSetting::hesabroClientId(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', Rule::in(array_keys(AppSetting::supportedCurrencies()))],
            'customer_verification_mode' => ['required', 'string', Rule::in(array_keys(AppSetting::customerVerificationModes()))],
            'national_code_verification_enabled' => ['required', 'boolean'],
            'national_code_verification_token' => ['nullable', 'string', 'max:255'],
            'sms_gateway' => ['required', 'string', Rule::in(array_keys(AppSetting::smsGateways()))],
            'sms0098_username' => ['nullable', 'string', 'max:255'],
            'sms0098_password' => ['nullable', 'string', 'max:255'],
            'sms0098_panel_no' => ['nullable', 'string', 'max:50'],
            'kavenegar_api_key' => ['nullable', 'string', 'max:255'],
            'kavenegar_template' => ['nullable', 'string', 'max:100'],
            'customer_wallet_negative_threshold' => ['nullable', 'integer'],
            'customer_wallet_negative_sms_enabled' => ['nullable', 'boolean'],
            'customer_wallet_negative_sms_template' => ['nullable', 'string', 'max:100'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(array_keys(AppSetting::smtpEncryptions()))],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'ticket_email_notifications_enabled' => ['nullable', 'boolean'],
            'ticket_sms_notifications_enabled' => ['nullable', 'boolean'],
            'ticket_kavenegar_customer_created_template' => ['nullable', 'string', 'max:100'],
            'ticket_kavenegar_admin_new_template' => ['nullable', 'string', 'max:100'],
            'ticket_kavenegar_customer_reply_template' => ['nullable', 'string', 'max:100'],
            'ticket_kavenegar_admin_reply_template' => ['nullable', 'string', 'max:100'],
            'ticket_kavenegar_assignment_template' => ['nullable', 'string', 'max:100'],
            'vm_creation_charge_enabled' => ['required', 'boolean'],
            'vm_creation_charge_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'unverified_customer_vm_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'verified_customer_vm_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'deleted_vm_cooldown_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'vm_rebuild_fee_multiplier_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'hetzner_usd_to_irr_rate' => ['nullable', 'integer', 'min:0'],
            'hetzner_price_markup_percentage' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'payments_enabled' => ['nullable', 'boolean'],
            'default_payment_gateway' => ['nullable', 'string', Rule::in(array_keys(AppSetting::paymentGateways()))],
            'mellat_payment_enabled' => ['nullable', 'boolean'],
            'mellat_payment_mode' => ['nullable', 'string', Rule::in(array_keys(AppSetting::mellatPaymentModes()))],
            'mellat_terminal_id' => ['nullable', 'integer', 'min:1'],
            'mellat_username' => ['nullable', 'string', 'max:255'],
            'mellat_password' => ['nullable', 'string', 'max:255'],
            'hesabro_payment_enabled' => ['nullable', 'boolean'],
            'hesabro_client' => ['nullable', 'string', 'max:255'],
            'hesabro_client_id' => ['nullable', 'string', 'max:255'],
            'hesabro_client_secret' => ['nullable', 'string', 'max:2000'],
        ]);

        $effectiveNationalCodeToken = $data['national_code_verification_token'] ?: (string) AppSetting::getValue(AppSetting::NATIONAL_CODE_VERIFICATION_TOKEN, '');

        if ($data['national_code_verification_enabled'] && $effectiveNationalCodeToken === '') {
            return back()
                ->withErrors(['national_code_verification_token' => 'توکن سرویس استعلام کد ملی الزامی است.'])
                ->withInput();
        }

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

        $mellatEnabled = (bool) ($data['mellat_payment_enabled'] ?? false);
        $hesabroEnabled = (bool) ($data['hesabro_payment_enabled'] ?? false);
        $paymentsEnabled = (bool) ($data['payments_enabled'] ?? false);
        $defaultPaymentGateway = $data['default_payment_gateway'] ?? AppSetting::defaultPaymentGateway();
        $effectiveMellatPassword = ($data['mellat_password'] ?? '') ?: AppSetting::mellatPassword();
        $effectiveHesabroClient = ltrim(trim((string) ($data['hesabro_client'] ?? AppSetting::hesabroClient())), '@');
        $effectiveHesabroSecret = ($data['hesabro_client_secret'] ?? '') ?: AppSetting::hesabroClientSecret();

        if ($mellatEnabled) {
            $mellatValidator = Validator::make([
                'mellat_terminal_id' => $data['mellat_terminal_id'] ?? null,
                'mellat_username' => $data['mellat_username'] ?? null,
                'mellat_password' => $effectiveMellatPassword,
            ], [
                'mellat_terminal_id' => ['required', 'integer', 'min:1'],
                'mellat_username' => ['required', 'string', 'max:255'],
                'mellat_password' => ['required', 'string', 'max:255'],
            ]);

            if ($mellatValidator->fails()) {
                return back()->withErrors($mellatValidator)->withInput();
            }
        }

        if ($hesabroEnabled) {
            $hesabroValidator = Validator::make([
                'hesabro_client' => $effectiveHesabroClient,
                'hesabro_client_id' => $data['hesabro_client_id'] ?? null,
                'hesabro_client_secret' => $effectiveHesabroSecret,
            ], [
                'hesabro_client' => ['required', 'string', 'max:255'],
                'hesabro_client_id' => ['required', 'string', 'max:255'],
                'hesabro_client_secret' => ['required', 'string', 'max:2000'],
            ]);

            if ($hesabroValidator->fails()) {
                return back()->withErrors($hesabroValidator)->withInput();
            }
        }

        if ($paymentsEnabled && ! $mellatEnabled && ! $hesabroEnabled) {
            return back()
                ->withErrors(['payments_enabled' => 'برای فعال‌سازی پرداخت آنلاین، حداقل یک درگاه باید فعال باشد.'])
                ->withInput();
        }

        $defaultGatewayEnabled = match ($defaultPaymentGateway) {
            'mellat' => $mellatEnabled,
            'hesabro' => $hesabroEnabled,
        };

        if ($paymentsEnabled && ! $defaultGatewayEnabled) {
            return back()
                ->withErrors(['default_payment_gateway' => 'درگاه پیش‌فرض باید فعال باشد.'])
                ->withInput();
        }

        AppSetting::setValue(AppSetting::BILLING_CURRENCY, $data['currency'], 'string', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFICATION_MODE, $data['customer_verification_mode'], 'string', 'customer');
        AppSetting::setValue(AppSetting::NATIONAL_CODE_VERIFICATION_ENABLED, (bool) $data['national_code_verification_enabled'], 'boolean', 'customer');
        AppSetting::setValue(AppSetting::SMS_GATEWAY, $data['sms_gateway'], 'string', 'sms');
        AppSetting::setValue(AppSetting::SMS0098_USERNAME, $data['sms0098_username'] ?? '', 'string', 'sms0098');
        AppSetting::setValue(AppSetting::SMS0098_PANEL_NO, $data['sms0098_panel_no'] ?? '', 'string', 'sms0098');
        AppSetting::setValue(AppSetting::KAVENEGAR_TEMPLATE, $data['kavenegar_template'] ?? '', 'string', 'kavenegar');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_THRESHOLD, (int) ($data['customer_wallet_negative_threshold'] ?? AppSetting::customerWalletNegativeThreshold()), 'integer', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_ENABLED, (bool) ($data['customer_wallet_negative_sms_enabled'] ?? false), 'boolean', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE, $data['customer_wallet_negative_sms_template'] ?? '', 'string', 'billing');
        AppSetting::setValue(AppSetting::SMTP_HOST, $data['smtp_host'] ?? '', 'string', 'smtp');
        AppSetting::setValue(AppSetting::SMTP_PORT, (int) ($data['smtp_port'] ?? 587), 'integer', 'smtp');
        AppSetting::setValue(AppSetting::SMTP_USERNAME, $data['smtp_username'] ?? '', 'string', 'smtp');
        AppSetting::setValue(AppSetting::SMTP_ENCRYPTION, $data['smtp_encryption'] ?? '', 'string', 'smtp');
        AppSetting::setValue(AppSetting::SMTP_FROM_ADDRESS, $data['smtp_from_address'] ?? config('mail.from.address'), 'string', 'smtp');
        AppSetting::setValue(AppSetting::SMTP_FROM_NAME, $data['smtp_from_name'] ?? config('mail.from.name'), 'string', 'smtp');
        AppSetting::setValue(AppSetting::TICKET_EMAIL_NOTIFICATIONS_ENABLED, (bool) ($data['ticket_email_notifications_enabled'] ?? false), 'boolean', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_SMS_NOTIFICATIONS_ENABLED, (bool) ($data['ticket_sms_notifications_enabled'] ?? false), 'boolean', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE, $data['ticket_kavenegar_customer_created_template'] ?? '', 'string', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE, $data['ticket_kavenegar_admin_new_template'] ?? '', 'string', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_CUSTOMER_REPLY_TEMPLATE, $data['ticket_kavenegar_customer_reply_template'] ?? '', 'string', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_ADMIN_REPLY_TEMPLATE, $data['ticket_kavenegar_admin_reply_template'] ?? '', 'string', 'ticketing');
        AppSetting::setValue(AppSetting::TICKET_KAVENEGAR_ASSIGNMENT_TEMPLATE, $data['ticket_kavenegar_assignment_template'] ?? '', 'string', 'ticketing');
        AppSetting::setValue(AppSetting::VM_CREATION_CHARGE_ENABLED, (bool) $data['vm_creation_charge_enabled'], 'boolean', 'billing');
        AppSetting::setValue(AppSetting::VM_CREATION_CHARGE_PERCENTAGE, (float) $data['vm_creation_charge_percentage'], 'float', 'billing');
        AppSetting::setValue(AppSetting::CUSTOMER_UNVERIFIED_VM_LIMIT, (int) $data['unverified_customer_vm_limit'], 'integer', 'customer');
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFIED_VM_LIMIT, (int) $data['verified_customer_vm_limit'], 'integer', 'customer');
        AppSetting::setValue(AppSetting::CUSTOMER_DELETED_VM_COOLDOWN_DAYS, (int) $data['deleted_vm_cooldown_days'], 'integer', 'customer');
        AppSetting::setValue(AppSetting::VM_REBUILD_FEE_MULTIPLIER_PERCENTAGE, (float) $data['vm_rebuild_fee_multiplier_percentage'], 'float', 'billing');
        AppSetting::setValue(AppSetting::HETZNER_USD_TO_IRR_RATE, (int) ($data['hetzner_usd_to_irr_rate'] ?? AppSetting::hetznerUsdToIrrRate()), 'integer', 'hetzner');
        AppSetting::setValue(AppSetting::HETZNER_PRICE_MARKUP_PERCENTAGE, (float) ($data['hetzner_price_markup_percentage'] ?? AppSetting::hetznerPriceMarkupPercentage()), 'float', 'hetzner');
        AppSetting::setValue(AppSetting::PAYMENTS_ENABLED, $paymentsEnabled, 'boolean', 'payment');
        AppSetting::setValue(AppSetting::DEFAULT_PAYMENT_GATEWAY, $defaultPaymentGateway, 'string', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_PAYMENT_ENABLED, $mellatEnabled, 'boolean', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_PAYMENT_MODE, $data['mellat_payment_mode'] ?? AppSetting::mellatPaymentMode(), 'string', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_TERMINAL_ID, (string) ($data['mellat_terminal_id'] ?? ''), 'string', 'payment');
        AppSetting::setValue(AppSetting::MELLAT_USERNAME, $data['mellat_username'] ?? '', 'string', 'payment');
        AppSetting::setValue(AppSetting::HESABRO_PAYMENT_ENABLED, $hesabroEnabled, 'boolean', 'payment');
        AppSetting::setValue(AppSetting::HESABRO_CLIENT, $effectiveHesabroClient, 'string', 'payment');
        AppSetting::setValue(AppSetting::HESABRO_CLIENT_ID, $data['hesabro_client_id'] ?? '', 'string', 'payment');

        if (! empty($data['sms0098_password'])) {
            AppSetting::setValue(AppSetting::SMS0098_PASSWORD, $data['sms0098_password'], 'string', 'sms0098');
        }

        if (! empty($data['kavenegar_api_key'])) {
            AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, $data['kavenegar_api_key'], 'string', 'kavenegar');
        }

        if (! empty($data['smtp_password'])) {
            AppSetting::setValue(AppSetting::SMTP_PASSWORD, $data['smtp_password'], 'string', 'smtp');
        }

        if (! empty($data['mellat_password'] ?? '')) {
            AppSetting::setValue(AppSetting::MELLAT_PASSWORD, $data['mellat_password'], 'string', 'payment');
        }

        if (! empty($data['hesabro_client_secret'] ?? '')) {
            AppSetting::setValue(AppSetting::HESABRO_CLIENT_SECRET, $data['hesabro_client_secret'], 'string', 'payment');
        }

        if ($effectiveNationalCodeToken !== '') {
            AppSetting::setValue(AppSetting::NATIONAL_CODE_VERIFICATION_TOKEN, $effectiveNationalCodeToken, 'string', 'customer');
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'type', 'group'])]
class AppSetting extends Model
{
    public const BILLING_CURRENCY = 'billing.currency';

    public const CUSTOMER_VERIFICATION_MODE = 'customer.verification.mode';

    public const NATIONAL_CODE_VERIFICATION_ENABLED = 'national_code.verification.enabled';

    public const NATIONAL_CODE_VERIFICATION_TOKEN = 'national_code.verification.token';

    public const SMS_GATEWAY = 'sms.gateway';

    public const SMS0098_USERNAME = 'sms0098.username';

    public const SMS0098_PASSWORD = 'sms0098.password';

    public const SMS0098_PANEL_NO = 'sms0098.panel_no';

    public const KAVENEGAR_API_KEY = 'kavenegar.api_key';

    public const KAVENEGAR_TEMPLATE = 'kavenegar.template';

    public const CUSTOMER_WALLET_NEGATIVE_THRESHOLD = 'customer.wallet.negative_threshold';

    public const CUSTOMER_WALLET_NEGATIVE_SMS_ENABLED = 'customer.wallet.negative_sms_enabled';

    public const CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE = 'customer.wallet.negative_sms_template';

    public const SMTP_HOST = 'smtp.host';

    public const SMTP_PORT = 'smtp.port';

    public const SMTP_USERNAME = 'smtp.username';

    public const SMTP_PASSWORD = 'smtp.password';

    public const SMTP_ENCRYPTION = 'smtp.encryption';

    public const SMTP_FROM_ADDRESS = 'smtp.from_address';

    public const SMTP_FROM_NAME = 'smtp.from_name';

    public const TICKET_EMAIL_NOTIFICATIONS_ENABLED = 'ticket.notifications.email_enabled';

    public const TICKET_SMS_NOTIFICATIONS_ENABLED = 'ticket.notifications.sms_enabled';

    public const TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE = 'ticket.kavenegar.customer_created_template';

    public const TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE = 'ticket.kavenegar.admin_new_template';

    public const TICKET_KAVENEGAR_CUSTOMER_REPLY_TEMPLATE = 'ticket.kavenegar.customer_reply_template';

    public const TICKET_KAVENEGAR_ADMIN_REPLY_TEMPLATE = 'ticket.kavenegar.admin_reply_template';

    public const TICKET_KAVENEGAR_ASSIGNMENT_TEMPLATE = 'ticket.kavenegar.assignment_template';

    public const VM_CREATION_CHARGE_ENABLED = 'vm.creation_charge.enabled';

    public const VM_CREATION_CHARGE_PERCENTAGE = 'vm.creation_charge.percentage';

    public const CUSTOMER_UNVERIFIED_VM_LIMIT = 'customer.level.unverified_vm_limit';

    public const CUSTOMER_VERIFIED_VM_LIMIT = 'customer.level.verified_vm_limit';

    public const CUSTOMER_DELETED_VM_COOLDOWN_DAYS = 'customer.level.deleted_vm_cooldown_days';

    public const VM_REBUILD_FEE_MULTIPLIER_PERCENTAGE = 'vm.rebuild_fee.multiplier_percentage';

    public const HETZNER_USD_TO_IRR_RATE = 'hetzner.usd_to_irr_rate';

    public const HETZNER_PRICE_MARKUP_PERCENTAGE = 'hetzner.price_markup_percentage';

    public const PAYMENTS_ENABLED = 'payment.enabled';

    public const DEFAULT_PAYMENT_GATEWAY = 'payment.default_gateway';

    public const MELLAT_PAYMENT_ENABLED = 'payment.mellat.enabled';

    public const MELLAT_PAYMENT_MODE = 'payment.mellat.mode';

    public const MELLAT_TERMINAL_ID = 'payment.mellat.terminal_id';

    public const MELLAT_USERNAME = 'payment.mellat.username';

    public const MELLAT_PASSWORD = 'payment.mellat.password';

    public const HESABRO_PAYMENT_ENABLED = 'payment.hesabro.enabled';

    public const HESABRO_CLIENT = 'payment.hesabro.client';

    public const HESABRO_CLIENT_ID = 'payment.hesabro.client_id';

    public const HESABRO_CLIENT_SECRET = 'payment.hesabro.client_secret';

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("settings.{$key}", function () use ($key, $default): mixed {
            $setting = static::query()->where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public static function setValue(string $key, mixed $value, string $type = 'string', string $group = 'general'): self
    {
        Cache::forget("settings.{$key}");

        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group],
        );
    }

    public static function currency(): string
    {
        return (string) static::getValue(self::BILLING_CURRENCY, 'IRR');
    }

    public static function supportedCurrencies(): array
    {
        return [
            'IRR' => 'IRR - ریال ایران',
            'IRT' => 'IRT - تومان ایران',
            'USD' => 'USD - US Dollar',
            'EUR' => 'EUR - Euro',
            'AED' => 'AED - UAE Dirham',
            'TRY' => 'TRY - Turkish Lira',
        ];
    }

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function customerVerificationMode(): string
    {
        $mode = (string) static::getValue(self::CUSTOMER_VERIFICATION_MODE, 'email');

        return in_array($mode, ['disabled', 'email', 'sms'], true) ? $mode : 'email';
    }

    public static function customerVerificationModes(): array
    {
        return [
            'disabled' => 'غیرفعال (بدون تایید)',
            'email' => 'تایید با ایمیل',
            'sms' => 'تایید با پیامک',
        ];
    }

    public static function nationalCodeVerificationEnabled(): bool
    {
        return filter_var(static::getValue(self::NATIONAL_CODE_VERIFICATION_ENABLED, true), FILTER_VALIDATE_BOOL);
    }

    public static function nationalCodeVerificationToken(): string
    {
        return (string) static::getValue(self::NATIONAL_CODE_VERIFICATION_TOKEN, '');
    }

    public static function smsGateway(): string
    {
        $gateway = (string) static::getValue(self::SMS_GATEWAY, 'sms0098');

        return in_array($gateway, array_keys(static::smsGateways()), true) ? $gateway : 'sms0098';
    }

    public static function smsGateways(): array
    {
        return [
            'sms0098' => 'SMS0098',
            'kavenegar' => 'Kavenegar Lookup',
        ];
    }

    public static function smtpEncryptions(): array
    {
        return [
            '' => 'بدون رمزنگاری',
            'tls' => 'TLS',
            'ssl' => 'SSL',
        ];
    }

    public static function mellatPaymentEnabled(): bool
    {
        return filter_var(static::getValue(self::MELLAT_PAYMENT_ENABLED, false), FILTER_VALIDATE_BOOL);
    }

    public static function paymentsEnabled(): bool
    {
        return filter_var(static::getValue(self::PAYMENTS_ENABLED, false), FILTER_VALIDATE_BOOL);
    }

    public static function defaultPaymentGateway(): string
    {
        $gateway = (string) static::getValue(self::DEFAULT_PAYMENT_GATEWAY, 'mellat');

        return array_key_exists($gateway, static::paymentGateways()) ? $gateway : 'mellat';
    }

    public static function paymentGateways(): array
    {
        return [
            'mellat' => 'بانک ملت',
            'hesabro' => 'حسابرو',
        ];
    }

    public static function mellatPaymentMode(): string
    {
        $mode = (string) static::getValue(self::MELLAT_PAYMENT_MODE, 'test');

        return in_array($mode, array_keys(static::mellatPaymentModes()), true) ? $mode : 'test';
    }

    public static function mellatPaymentModes(): array
    {
        return [
            'test' => 'تست',
            'production' => 'عملیاتی',
        ];
    }

    public static function mellatTerminalId(): string
    {
        return (string) static::getValue(self::MELLAT_TERMINAL_ID, '');
    }

    public static function mellatUsername(): string
    {
        return (string) static::getValue(self::MELLAT_USERNAME, '');
    }

    public static function mellatPassword(): string
    {
        return (string) static::getValue(self::MELLAT_PASSWORD, '');
    }

    public static function mellatPaymentConfigured(): bool
    {
        return static::mellatTerminalId() !== ''
            && static::mellatUsername() !== ''
            && static::mellatPassword() !== '';
    }

    public static function hesabroPaymentEnabled(): bool
    {
        return filter_var(static::getValue(self::HESABRO_PAYMENT_ENABLED, false), FILTER_VALIDATE_BOOL);
    }

    public static function hesabroClient(): string
    {
        $client = trim((string) static::getValue(self::HESABRO_CLIENT, ''));

        return ltrim($client, '@');
    }

    public static function hesabroClientId(): string
    {
        return (string) static::getValue(self::HESABRO_CLIENT_ID, '');
    }

    public static function hesabroClientSecret(): string
    {
        return (string) static::getValue(self::HESABRO_CLIENT_SECRET, '');
    }

    public static function hesabroPaymentConfigured(): bool
    {
        return static::hesabroClient() !== '' && static::hesabroClientId() !== '' && static::hesabroClientSecret() !== '';
    }

    public static function ticketEmailNotificationsEnabled(): bool
    {
        return filter_var(static::getValue(self::TICKET_EMAIL_NOTIFICATIONS_ENABLED, false), FILTER_VALIDATE_BOOL);
    }

    public static function ticketSmsNotificationsEnabled(): bool
    {
        return filter_var(static::getValue(self::TICKET_SMS_NOTIFICATIONS_ENABLED, false), FILTER_VALIDATE_BOOL);
    }

    public static function vmCreationChargeEnabled(): bool
    {
        return filter_var(static::getValue(self::VM_CREATION_CHARGE_ENABLED, false), FILTER_VALIDATE_BOOL);
    }

    public static function vmCreationChargePercentage(): float
    {
        $percentage = (float) static::getValue(self::VM_CREATION_CHARGE_PERCENTAGE, 0);

        return max(0, min(100, $percentage));
    }

    public static function vmCreationChargeAmount(int $monthlyPrice): int
    {
        if (! static::vmCreationChargeEnabled()) {
            return 0;
        }

        return (int) round($monthlyPrice * static::vmCreationChargePercentage() / 100);
    }

    public static function unverifiedCustomerVmLimit(): int
    {
        return max(0, (int) static::getValue(self::CUSTOMER_UNVERIFIED_VM_LIMIT, 2));
    }

    public static function verifiedCustomerVmLimit(): int
    {
        return max(0, (int) static::getValue(self::CUSTOMER_VERIFIED_VM_LIMIT, 0));
    }

    public static function deletedVmCooldownDays(): int
    {
        return max(0, (int) static::getValue(self::CUSTOMER_DELETED_VM_COOLDOWN_DAYS, 30));
    }

    public static function vmRebuildFeeMultiplierPercentage(): float
    {
        $percentage = (float) static::getValue(self::VM_REBUILD_FEE_MULTIPLIER_PERCENTAGE, 50);

        return max(0, min(100, $percentage));
    }

    public static function vmRebuildFeeAmount(int $monthlyPrice): int
    {
        $creationCharge = static::vmCreationChargeAmount($monthlyPrice);

        if ($creationCharge <= 0) {
            return 0;
        }

        return (int) round($creationCharge * static::vmRebuildFeeMultiplierPercentage() / 100);
    }

    public static function hetznerUsdToIrrRate(): int
    {
        return max(0, (int) static::getValue(self::HETZNER_USD_TO_IRR_RATE, 0));
    }

    public static function hetznerPriceMarkupPercentage(): float
    {
        $percentage = (float) static::getValue(self::HETZNER_PRICE_MARKUP_PERCENTAGE, 0);

        return max(0, $percentage);
    }

    public static function convertHetznerUsdToIrr(float $usd): int
    {
        $rate = static::hetznerUsdToIrrRate();

        if ($rate <= 0) {
            return 0;
        }

        $markup = 1 + (static::hetznerPriceMarkupPercentage() / 100);

        return (int) round($usd * $rate * $markup);
    }

    public static function customerWalletNegativeThreshold(): int
    {
        return (int) static::getValue(self::CUSTOMER_WALLET_NEGATIVE_THRESHOLD, 0);
    }

    public static function customerWalletNegativeSmsEnabled(): bool
    {
        return filter_var(static::getValue(self::CUSTOMER_WALLET_NEGATIVE_SMS_ENABLED, true), FILTER_VALIDATE_BOOL);
    }

    public static function customerWalletNegativeSmsTemplate(): string
    {
        return (string) static::getValue(self::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE, '');
    }
}

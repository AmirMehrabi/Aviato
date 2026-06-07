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
}

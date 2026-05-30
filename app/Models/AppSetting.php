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

    public const SMS_GATEWAY = 'sms.gateway';

    public const SMS0098_USERNAME = 'sms0098.username';

    public const SMS0098_PASSWORD = 'sms0098.password';

    public const SMS0098_PANEL_NO = 'sms0098.panel_no';

    public const KAVENEGAR_API_KEY = 'kavenegar.api_key';

    public const KAVENEGAR_TEMPLATE = 'kavenegar.template';

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
}

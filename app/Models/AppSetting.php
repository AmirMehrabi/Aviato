<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'type', 'group'])]
class AppSetting extends Model
{
    public const BILLING_CURRENCY = 'billing.currency';

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
}

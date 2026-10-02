<?php

namespace App\Enums;

enum AdminRole: string
{
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Support = 'support';
    case Infrastructure = 'infrastructure';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدیر',
            self::Accountant => 'حسابدار',
            self::Support => 'پشتیبانی',
            self::Infrastructure => 'زیرساخت',
            self::Custom => 'دسترسی سفارشی',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])->all();
    }
}

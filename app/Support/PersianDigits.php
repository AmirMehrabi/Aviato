<?php

namespace App\Support;

class PersianDigits
{
    public static function convert(?string $value): string
    {
        return strtr($value ?? '', [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    public static function format(int|float $value, int $decimals = 0): string
    {
        return self::convert(number_format($value, $decimals));
    }
}

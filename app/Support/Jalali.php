<?php

namespace App\Support;

use Morilog\Jalali\Jalalian;

class Jalali
{
    public static function now(): Jalalian
    {
        return Jalalian::now();
    }

    public static function currentJalaliMonthRange(): array
    {
        $now = Jalalian::now();
        $start = $now->getFirstDayOfMonth()->toCarbon()->startOfDay();
        $end = $now->getEndDayOfMonth()->toCarbon()->endOfDay();

        return [$start, $end];
    }

    public static function formatMonthYear(int $year, int $month): string
    {
        return self::formatMonthName($month).' '.$year;
    }

    public static function formatMonthName(int $month): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return $months[$month] ?? '';
    }

    public static function formatMonthYearFromNow(): string
    {
        $now = Jalalian::now();

        return self::formatMonthName($now->getMonth()).' '.$now->getYear();
    }
}

<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class Jalali
{
    private static array $gMonthDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

    public static function gToJ(int $gY, int $gM, int $gD): array
    {
        $gy = $gY - 1600;
        $gm = $gM - 1;
        $gd = $gD - 1;

        $gDayNo = 365 * $gy + self::div($gy + 3, 4) - self::div($gy + 99, 100) + self::div($gy + 399, 400);
        $gDayNo += self::$gMonthDays[$gm] + $gd;

        $jDayNo = $gDayNo - 79;

        $jNp = self::div($jDayNo, 12053);
        $jDayNo %= 12053;

        $jY = 979 + 33 * $jNp + 4 * self::div($jDayNo, 1461);
        $jDayNo %= 1461;

        if ($jDayNo >= 366) {
            $jY += self::div($jDayNo - 1, 365);
            $jDayNo = ($jDayNo - 1) % 365;
        }

        if ($jDayNo < 186) {
            $jM = 1 + self::div($jDayNo, 31);
            $jD = 1 + ($jDayNo % 31);
        } else {
            $jM = 7 + self::div($jDayNo - 186, 30);
            $jD = 1 + (($jDayNo - 186) % 30);
        }

        return [$jY, $jM, $jD];
    }

    public static function jToG(int $jY, int $jM, int $jD): array
    {
        $jy = $jY - 979;
        $jm = $jM - 1;
        $jd = $jD - 1;

        $jDayNo = 365 * $jy + self::div($jy, 33) * 8 + self::div(($jm < 7 ? $jm : $jm - 7) + 1, 11);
        $jDayNo += self::jMonthDays($jm) + $jd;

        $gDayNo = $jDayNo + 79;

        $gY = 1600 + 400 * self::div($gDayNo, 146097);
        $gDayNo %= 146097;

        if ($gDayNo >= 36525) {
            $gDayNo--;
            $gY += 100 * self::div($gDayNo, 36524);
            $gDayNo %= 36524;
            if ($gDayNo >= 365) {
                $gDayNo++;
            }
        }

        $gY += self::div($gDayNo, 365);
        $gDayNo %= 365;

        $gD = $gDayNo + 1;

        foreach ([31, (self::isLeapYear($gY) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31] as $index => $days) {
            if ($gDayNo < $days) {
                $gM = $index + 1;
                break;
            }
            $gDayNo -= $days;
        }

        return [$gY, $gM, $gD + 1];
    }

    public static function nowJalai(): array
    {
        $now = CarbonImmutable::now();

        return self::gToJ((int) $now->format('Y'), (int) $now->format('m'), (int) $now->format('d'));
    }

    public static function currentJalaliMonthRange(): array
    {
        [$jY, $jM] = self::nowJalai();

        [$gStartY, $gStartM, $gStartD] = self::jToG($jY, $jM, 1);
        [$gEndY, $gEndM, $gEndD] = self::jToG($jY, $jM, self::jalaliMonthDays($jM));

        return [
            CarbonImmutable::create($gStartY, $gStartM, $gStartD)->startOfDay(),
            CarbonImmutable::create($gEndY, $gEndM, $gEndD)->endOfDay(),
        ];
    }

    public static function jalaliMonthDays(int $month): int
    {
        return $month <= 6 ? 31 : ($month <= 11 ? 30 : 29);
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

    public static function formatMonthYear(int $year, int $month): string
    {
        return self::formatMonthName($month).' '.$year;
    }

    private static function div(int $a, int $b): int
    {
        return (int) floor($a / $b);
    }

    private static function jMonthDays(int $month): int
    {
        return $month <= 6 ? 31 * $month : 31 * 6 + 30 * ($month - 6);
    }

    private static function isLeapYear(int $year): bool
    {
        return ($year % 4 == 0 && $year % 100 != 0) || ($year % 400 == 0);
    }
}

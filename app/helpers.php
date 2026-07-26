<?php

use App\Support\Jalali;
use Carbon\CarbonInterface;

if (! function_exists('jdf')) {
    function jdf(?CarbonInterface $date, string $format = 'Y/m/d H:i'): string
    {
        return Jalali::format($date, $format);
    }
}

if (! function_exists('formatMinutesFa')) {
    function formatMinutesFa(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' دقیقه';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours.' ساعت'.($remaining ? ' و '.$remaining.' دقیقه' : '');
    }
}

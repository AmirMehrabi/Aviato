<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\CarbonInterface;

class WorkspaceWalletQuietHours
{
    public function isQuiet(?CarbonInterface $at = null): bool
    {
        $local = ($at ?? now())->copy()->setTimezone(config('app.customer_timezone', 'Asia/Tehran'));
        $time = $local->format('H:i');
        $start = AppSetting::customerWalletQuietStart();
        $end = AppSetting::customerWalletQuietEnd();

        if ($start === $end) {
            return false;
        }

        return $start < $end
            ? $time >= $start && $time < $end
            : $time >= $start || $time < $end;
    }
}

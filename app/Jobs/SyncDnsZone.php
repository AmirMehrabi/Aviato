<?php

namespace App\Jobs;

use App\Models\DnsZone;
use App\Services\CloudDns\ZoneSynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncDnsZone implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 150;

    public function __construct(public int $zoneId) {}

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120, 300, 600];
    }

    public function handle(ZoneSynchronizer $sync): void
    {
        try {
            $sync->sync($this->zoneId);
        } catch (Throwable $exception) {
            // Preserve lifecycle status, especially suspension/deletion, on partial failure.
            DnsZone::whereKey($this->zoneId)->update(['last_error' => 'همگام‌سازی کامل نشد؛ تلاش مجدد به‌صورت خودکار انجام می‌شود.']);
            throw $exception;
        }
    }
}

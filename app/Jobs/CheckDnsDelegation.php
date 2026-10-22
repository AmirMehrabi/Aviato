<?php

namespace App\Jobs;

use App\Models\DnsZone;
use App\Services\CloudDns\CloudDnsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckDnsDelegation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 45;

    public int $tries = 2;

    public function __construct(public int $zoneId) {}

    public function handle(CloudDnsService $dns): void
    {
        $zone = DnsZone::find($this->zoneId);
        if ($zone && $zone->verified_at && ! $zone->delete_requested_at && $zone->status !== 'deleted') {
            $dns->check($zone);
        }
    }
}

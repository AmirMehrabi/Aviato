<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class NotifyIpdrInventory implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [10, 60, 120];
    }

    public function handle(): void
    {
        if (! config('services.ipdr.integration_token') || ! config('services.ipdr.url')) {
            return;
        }
        Http::baseUrl(rtrim(config('services.ipdr.url'), '/'))->acceptJson()
            ->withToken(config('services.ipdr.integration_token'))->connectTimeout(5)->timeout(15)
            ->post('/api/internal/v1/integration/inventory-sync')->throw();
    }
}

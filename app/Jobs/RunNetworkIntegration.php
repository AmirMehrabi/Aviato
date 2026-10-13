<?php

namespace App\Jobs;

use App\Models\NetworkIntegrationRun;
use App\Services\IpdrClient;
use App\Services\NetworkUsageIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RunNetworkIntegration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public ?int $retryDeadline = null;

    public int $timeout = 300;

    public function __construct(public int $runId)
    {
        $this->retryDeadline = now()->addHour()->timestamp;
    }

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->retryDeadline ?? now()->addHour()->timestamp);
    }

    public function backoff(): array
    {
        return [15, 60, 120];
    }

    public function handle(NetworkUsageIngestionService $ingestion, IpdrClient $client): void
    {
        Cache::lock('network-integration-run:'.$this->runId, 600)->block(3, function () use ($ingestion, $client): void {
            $run = NetworkIntegrationRun::findOrFail($this->runId);
            if ($run->status === 'completed') {
                return;
            }
            $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
            try {
                if ($run->kind === 'delivery') {
                    $result = $ingestion->receive($run->payload['items']);
                    $run->update(['stats' => $result['stats'], 'errors' => $result['errors'], 'rejected_count' => count($result['errors'])]);
                } else {
                    try {
                        $run->update(['health' => $client->health()]);
                    } catch (\Throwable $e) {
                        $run->update(['health' => ['unavailable' => true]]);
                        report($e);
                    }
                    $from = $run->payload['from'] ?? null;
                    $to = $run->payload['to'] ?? null;
                    $stats = $ingestion->sync($from, $to, 3, $run->payload['cursor'] ?? null);
                    $run->update(['stats' => $stats]);
                    if (($stats['has_more'] ?? false) && $from && $to) {
                        $run->update(['payload' => ['from' => $from, 'to' => $to, 'cursor' => $stats['next_cursor']]]);
                        // continuation is handled below by a separate durable run
                        $next = NetworkIntegrationRun::create(['kind' => 'backfill', 'payload' => $run->payload]);
                        self::dispatch($next->id);
                    }
                }
                $run->update(['status' => 'completed', 'finished_at' => now()]);
            } catch (LockTimeoutException $e) {
                $run->update(['status' => 'queued', 'started_at' => null, 'error' => null]);
                $this->release(15);
            } catch (\Throwable $e) {
                $run->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
                throw $e;
            }
        });
    }

    public function failed(?\Throwable $exception): void
    {
        NetworkIntegrationRun::whereKey($this->runId)->update(['status' => 'failed', 'error' => $exception?->getMessage() ?? 'Worker stopped.', 'finished_at' => now()]);
    }
}

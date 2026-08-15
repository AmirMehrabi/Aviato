<?php

namespace App\Services;

use App\Models\NetworkIngestionCheckpoint;
use App\Models\NetworkMeterAssignment;
use App\Models\NetworkUsageBucket;
use App\Models\VirtualMachine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class NetworkUsageIngestionService
{
    public function __construct(private readonly IpdrClient $client, private readonly NetworkUsageRatingService $rating) {}

    /** @return array{pages:int,received:int,changed:int,rated:int} */
    public function sync(?string $from = null, ?string $to = null, int $maxPages = 100): array
    {
        $source = (string) config('services.ipdr.source', 'ipdr');
        $checkpoint = NetworkIngestionCheckpoint::query()->firstOrCreate(['source' => $source]);
        $cursor = $from || $to ? null : $checkpoint->cursor;
        $stats = ['pages' => 0, 'received' => 0, 'changed' => 0, 'rated' => 0];
        $checkpoint->forceFill(['last_attempted_at' => now(), 'last_error' => null])->save();

        try {
            do {
                $page = $this->client->buckets($cursor, 500, $from, $to);
                foreach ($page['items'] as $payload) {
                    $stats['received']++;
                    $bucket = $this->ingest($source, $payload);
                    if ($bucket) {
                        $stats['changed']++;
                        if ($this->rating->rate($bucket)) {
                            $stats['rated']++;
                        }
                    }
                }
                $stats['pages']++;
                $cursor = $page['next_cursor'] ?? null;
                if (! $from && ! $to) {
                    $checkpoint->forceFill(['cursor' => $cursor, 'last_succeeded_at' => now()])->save();
                }
            } while (($page['has_more'] ?? false) && $cursor && $stats['pages'] < $maxPages);
        } catch (\Throwable $e) {
            $checkpoint->forceFill(['last_error' => $e->getMessage()])->save();
            throw $e;
        }

        return $stats;
    }

    public function ingest(string $source, array $payload): ?NetworkUsageBucket
    {
        $data = Validator::make($payload, [
            'bucket_id' => ['required', 'string', 'max:128'],
            'revision' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['provisional', 'final', 'void'])],
            'vm_uuid' => ['required', 'uuid'],
            'assignment_id' => ['required', 'string', 'max:128'],
            'interval_start' => ['required', 'date'],
            'interval_end' => ['required', 'date', 'after:interval_start'],
            'ingress_bytes' => ['required', 'integer', 'min:0'],
            'egress_bytes' => ['required', 'integer', 'min:0'],
            'completeness' => ['required', Rule::in(['complete', 'partial', 'missing'])],
            'calculation_version' => ['nullable', 'string', 'max:80'],
            'finalized_at' => ['nullable', 'date'],
            'updated_at' => ['required', 'date'],
        ])->validate();

        $start = CarbonImmutable::parse($data['interval_start'])->utc();
        $end = CarbonImmutable::parse($data['interval_end'])->utc();
        if ($start->diffInHours($end) > 24) {
            throw new RuntimeException('IPDR bucket interval cannot exceed 24 hours.');
        }

        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $canonical);

        return DB::transaction(function () use ($source, $data, $payload, $hash, $start, $end): ?NetworkUsageBucket {
            $vm = VirtualMachine::query()->where('uuid', $data['vm_uuid'])->first();
            $assignment = NetworkMeterAssignment::query()
                ->where('source', $source)->where('assignment_id', $data['assignment_id'])->lockForUpdate()->first();
            if ($assignment && $assignment->vm_uuid !== $data['vm_uuid']) {
                throw new RuntimeException('IPDR assignment_id was reused for a different VM UUID.');
            }
            if (! $assignment) {
                $assignment = NetworkMeterAssignment::query()->create([
                    'source' => $source, 'assignment_id' => $data['assignment_id'],
                    'virtual_machine_id' => $vm?->id, 'vm_uuid' => $data['vm_uuid'],
                    'first_observed_at' => $start, 'last_observed_at' => $end,
                ]);
            } else {
                $assignment->forceFill([
                    'virtual_machine_id' => $assignment->virtual_machine_id ?? $vm?->id,
                    'first_observed_at' => min($assignment->first_observed_at, $start),
                    'last_observed_at' => max($assignment->last_observed_at, $end),
                ])->save();
            }
            $bucket = NetworkUsageBucket::query()->where('source', $source)->where('bucket_id', $data['bucket_id'])->lockForUpdate()->first();

            if ($bucket && (int) $data['revision'] < $bucket->revision) {
                return null;
            }
            if ($bucket && (int) $data['revision'] === $bucket->revision) {
                if (! hash_equals($bucket->payload_hash, $hash)) {
                    throw new RuntimeException('IPDR sent conflicting content for the same bucket revision.');
                }

                return null;
            }

            $bucket ??= new NetworkUsageBucket(['source' => $source, 'bucket_id' => $data['bucket_id']]);
            $bucket->fill([
                'revision' => $data['revision'], 'status' => $data['status'], 'virtual_machine_id' => $vm?->id,
                'vm_uuid' => $data['vm_uuid'], 'assignment_id' => $data['assignment_id'],
                'interval_start' => $start, 'interval_end' => $end,
                'ingress_bytes' => $data['ingress_bytes'], 'egress_bytes' => $data['egress_bytes'],
                'completeness' => $data['completeness'], 'calculation_version' => $data['calculation_version'] ?? null,
                'finalized_at' => $data['finalized_at'] ?? null, 'source_updated_at' => $data['updated_at'],
                'payload_hash' => $hash, 'payload' => $payload,
                'processing_status' => $vm ? 'pending' : 'quarantined',
                'processing_error' => $vm ? null : 'Unknown vm_uuid.', 'rated_at' => null,
            ])->save();
            $bucket->revisions()->create(['revision' => $data['revision'], 'payload_hash' => $hash, 'payload' => $payload]);

            return $bucket->refresh();
        });
    }
}

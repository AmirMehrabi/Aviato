<?php

namespace App\Services;

use App\Models\NetworkUsageBucket;

class NetworkUsageReconciliationService
{
    public function __construct(private readonly IpdrClient $client) {}

    /** @return array<int, array<string, mixed>> */
    public function reconcile(string $from, string $to, ?string $vmUuid = null): array
    {
        $remote = $this->client->summaries($from, $to, $vmUuid);
        $remoteItems = collect($remote['items'] ?? (isset($remote['vm_uuid']) ? [$remote] : []))->keyBy('vm_uuid');
        $localItems = NetworkUsageBucket::query()
            ->where('source', config('services.ipdr.source', 'ipdr'))
            ->where('status', 'final')->where('completeness', 'complete')
            ->where('interval_start', '>=', $from)->where('interval_end', '<=', $to)
            ->when($vmUuid, fn ($query) => $query->where('vm_uuid', $vmUuid))
            ->groupBy('vm_uuid')
            ->selectRaw('vm_uuid, COALESCE(SUM(ingress_bytes), 0) as ingress_bytes, COALESCE(SUM(egress_bytes), 0) as egress_bytes, COUNT(*) as final_bucket_count')
            ->get()->keyBy('vm_uuid');

        return $remoteItems->keys()->merge($localItems->keys())->unique()->sort()->map(function (string $uuid) use ($remoteItems, $localItems): array {
            $remote = $remoteItems->get($uuid, []);
            $local = $localItems->get($uuid);
            $remoteIngress = (int) ($remote['ingress_bytes'] ?? 0);
            $remoteEgress = (int) ($remote['egress_bytes'] ?? 0);
            $localIngress = (int) ($local?->ingress_bytes ?? 0);
            $localEgress = (int) ($local?->egress_bytes ?? 0);

            return [
                'vm_uuid' => $uuid,
                'remote_ingress_bytes' => $remoteIngress,
                'local_ingress_bytes' => $localIngress,
                'remote_egress_bytes' => $remoteEgress,
                'local_egress_bytes' => $localEgress,
                'remote_bucket_count' => (int) ($remote['final_bucket_count'] ?? 0),
                'local_bucket_count' => (int) ($local?->final_bucket_count ?? 0),
                'matches' => $remoteIngress === $localIngress && $remoteEgress === $localEgress
                    && (int) ($remote['final_bucket_count'] ?? 0) === (int) ($local?->final_bucket_count ?? 0),
            ];
        })->values()->all();
    }
}

<?php

namespace App\Services;

use App\Models\CloudImage;
use App\Models\CloudImageNodeMapping;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProxmoxPlacementService
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
        private readonly IpPoolService $ipPools,
    ) {}

    /**
     * @param  array{cpu_cores:int,ram_gb:int,disk_gb:int}  $resources
     * @return array{mapping:CloudImageNodeMapping, snapshot:array<string,mixed>}
     */
    public function select(CloudImage $image, array $resources, ?string $preferredNode = null): array
    {
        $server = $image->proxmoxServer;

        if (! $server || ! $server->is_active || $server->maintenance_mode) {
            throw new RuntimeException('The selected Proxmox cluster is unavailable for placement.');
        }

        $mappings = $image->nodeMappings()
            ->where('proxmox_server_id', $server->id)
            ->where('is_enabled', true)
            ->get();

        if ($mappings->isEmpty() && filled($image->node) && $image->template_vmid) {
            $legacyMapping = new CloudImageNodeMapping([
                'cloud_image_id' => $image->id,
                'proxmox_server_id' => $server->id,
                'node' => $preferredNode ?: $image->node,
                'template_vmid' => $image->template_vmid,
                'storage' => $preferredNode ? null : $image->storage,
                'network_bridge' => $image->network_bridge ?: 'vmbr1',
                'is_enabled' => true,
            ]);

            return [
                'mapping' => $legacyMapping,
                'snapshot' => [
                    'selected_at' => now()->toISOString(),
                    'node' => $legacyMapping->node,
                    'template_vmid' => $legacyMapping->template_vmid,
                    'template_version' => null,
                    'storage' => $legacyMapping->storage,
                    'network_bridge' => $legacyMapping->network_bridge,
                    'score' => null,
                    'metrics' => [],
                    'rejected_nodes' => [],
                    'legacy_mapping' => true,
                ],
            ];
        }

        if ($preferredNode) {
            $mappings = $mappings->where('node', $preferredNode)->values();
        }

        if ($mappings->isEmpty()) {
            throw new RuntimeException($preferredNode
                ? "The requested node {$preferredNode} is not enabled for this image."
                : 'No Proxmox node mapping is enabled for this image.');
        }

        try {
            $inventory = $this->proxmox->vmCreationOptions($server);
        } catch (Throwable $exception) {
            throw new RuntimeException('Proxmox capacity could not be checked: '.$exception->getMessage(), previous: $exception);
        }

        $nodes = collect($inventory['nodes'] ?? [])->keyBy('name');
        $templates = collect($inventory['os_templates'] ?? []);
        $storages = collect($inventory['disk_storages'] ?? []);
        $bridges = collect($inventory['bridges'] ?? []);
        $rejected = [];
        $eligible = [];

        foreach ($mappings as $mapping) {
            $node = $nodes->get($mapping->node);
            $reasons = [];

            if (! $node || data_get($node, 'raw.status') !== 'online') {
                $reasons[] = 'node is offline';
            }

            if (! $templates->contains(fn (array $template): bool => $template['node'] === $mapping->node
                && (int) $template['vmid'] === (int) $mapping->template_vmid)) {
                $reasons[] = 'template is unavailable';
            }

            $storage = $storages->first(fn (array $storage): bool => $storage['node'] === $mapping->node
                && $storage['storage'] === $mapping->storage);
            if (! $storage) {
                $reasons[] = 'storage is unavailable';
            }

            if (! $bridges->contains(fn (array $bridge): bool => $bridge['node'] === $mapping->node
                && $bridge['iface'] === $mapping->network_bridge)) {
                $reasons[] = 'network bridge is unavailable';
            }

            if ($this->ipPools->availableCountFor((int) $server->id, $mapping->node) < 1) {
                $reasons[] = 'IP pool is exhausted';
            }

            $committed = VirtualMachine::query()
                ->where('proxmox_server_id', $server->id)
                ->where('node', $mapping->node)
                ->notDeleted()
                ->selectRaw('COALESCE(SUM(cpu_cores), 0) AS cpu, COALESCE(SUM(ram_gb), 0) AS ram, COALESCE(SUM(disk_gb), 0) AS disk')
                ->first();

            $maxCpu = max(1, (int) data_get($node, 'raw.maxcpu', 1));
            $maxRamGb = max(1, (float) data_get($node, 'raw.maxmem', 0) / 1073741824);
            $cpuLive = round((float) data_get($node, 'raw.cpu', 0) * 100, 2);
            $cpuProjected = round((((int) $committed->cpu + $resources['cpu_cores']) / $maxCpu) * 100, 2);
            $ramLive = round(((float) data_get($node, 'raw.mem', 0) / max(1, (float) data_get($node, 'raw.maxmem', 1))) * 100, 2);
            $ramProjected = round((((int) $committed->ram + $resources['ram_gb']) / $maxRamGb) * 100, 2);
            $storageTotal = max(1, (float) ($storage['total'] ?? 0));
            $storageUsed = (float) ($storage['used'] ?? max(0, $storageTotal - (float) ($storage['avail'] ?? 0)));
            $diskProjected = round((($storageUsed + ($resources['disk_gb'] * 1073741824)) / $storageTotal) * 100, 2);

            if ($cpuLive >= (int) $server->cpu_threshold_percent) {
                $reasons[] = 'CPU threshold would be exceeded';
            }
            if (max($ramLive, $ramProjected) >= (int) $server->ram_threshold_percent) {
                $reasons[] = 'RAM threshold would be exceeded';
            }
            if ($diskProjected >= (int) $server->disk_threshold_percent) {
                $reasons[] = 'disk threshold would be exceeded';
            }

            $metrics = compact('cpuLive', 'cpuProjected', 'ramLive', 'ramProjected', 'diskProjected');

            if ($reasons !== []) {
                $rejected[$mapping->node] = $reasons;

                continue;
            }

            $score = round(
                max($cpuLive, $cpuProjected) * 0.30
                + max($ramLive, $ramProjected) * 0.40
                + $diskProjected * 0.30,
                3,
            );

            $eligible[] = compact('mapping', 'score', 'metrics');
        }

        usort($eligible, fn (array $left, array $right): int => [$left['score'], $left['mapping']->node]
            <=> [$right['score'], $right['mapping']->node]);

        if ($eligible === []) {
            $details = collect($rejected)
                ->map(fn (array $reasons, string $node): string => $node.': '.implode(', ', $reasons))
                ->implode('; ');

            throw new RuntimeException('No Proxmox node has enough compatible capacity. '.$details);
        }

        $selected = $eligible[0];

        if ($selected['mapping']->exists) {
            DB::transaction(fn () => CloudImageNodeMapping::query()
                ->whereKey($selected['mapping']->id)
                ->lockForUpdate()
                ->firstOrFail());
        }

        return [
            'mapping' => $selected['mapping'],
            'snapshot' => [
                'selected_at' => now()->toISOString(),
                'node' => $selected['mapping']->node,
                'template_vmid' => $selected['mapping']->template_vmid,
                'template_version' => $selected['mapping']->template_version,
                'storage' => $selected['mapping']->storage,
                'network_bridge' => $selected['mapping']->network_bridge,
                'score' => $selected['score'],
                'metrics' => $selected['metrics'],
                'rejected_nodes' => $rejected,
            ],
        ];
    }
}

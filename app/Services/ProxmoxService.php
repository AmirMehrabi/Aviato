<?php

namespace App\Services;

use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProxmoxService
{
    private ?string $operationId = null;

    /**
     * Fetch a compact cluster summary for admin/API show pages.
     *
     * @return array<string, mixed>
     */
    public function summary(ProxmoxServer $server): array
    {
        return $this->runWithOperation($server, 'summary', fn (): array => $this->fetchSummary($server));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSummary(ProxmoxServer $server): array
    {
        $errors = [];
        $diagnosticErrors = [];

        try {
            // Treat /nodes as the connectivity/auth source of truth because scoped API tokens often allow it while denying /version.
            $nodes = $this->getData($server, '/nodes') ?? [];
            $version = $this->getOptionalData($server, '/version', $errors);
            $clusterStatus = $this->getOptionalData($server, '/cluster/status', $diagnosticErrors, []);
            $resources = $this->vmInventory($server, $nodes, $errors);
            $storage = $this->storageInventory($server, $nodes, $errors);
            $backups = $this->backupInventory($server, $storage, $errors);
        } catch (ConnectionException $exception) {
            $this->logConnectionDiagnostics($server, $exception);

            throw new RuntimeException('Unable to connect to the Proxmox API: '.$exception->getMessage(), previous: $exception);
        } catch (RequestException $exception) {
            $this->logHttpFailure($server, 'summary fatal endpoint', $exception);

            throw new RuntimeException('Unable to authenticate with the Proxmox API: HTTP '.$exception->response->status().' for '.$exception->response->effectiveUri(), previous: $exception);
        }

        return [
            'version' => $version,
            'nodes' => $nodes,
            'cluster_status' => $clusterStatus,
            'virtual_machines' => $resources,
            'storage' => $storage,
            'backups' => $backups,
            'endpoint_errors' => $errors,
            'diagnostic_endpoint_errors' => $diagnosticErrors,
            'counts' => $this->counts($nodes, $resources, $storage, $backups),
            'fetched_at' => now()->toISOString(),
        ];
    }

    /**
     * Apply locally stored desired state once the remote endpoint is reachable.
     * This currently syncs metadata by recording a successful API touch; VM-level actions can be added here.
     *
     * @return array<string, mixed>
     */
    public function syncDesiredState(ProxmoxServer $server): array
    {
        return $this->runWithOperation($server, 'sync', function () use ($server): array {
            $summary = $this->fetchSummary($server);

            $server->forceFill([
                'connection_status' => ProxmoxServer::CONNECTION_ONLINE,
                'sync_status' => ProxmoxServer::SYNC_SYNCED,
                'sync_error' => null,
                'sync_pending_since' => null,
                'synced_at' => now(),
                'last_seen_at' => now(),
                'remote_inventory' => $summary,
                'last_status' => [
                    'counts' => $summary['counts'],
                    'version' => $summary['version'],
                    'fetched_at' => $summary['fetched_at'],
                ],
            ])->save();

            $this->logInfo('Proxmox sync state saved', $server, [
                'counts' => $summary['counts'],
                'endpoint_error_count' => count($summary['endpoint_errors'] ?? []),
                'diagnostic_endpoint_error_count' => count($summary['diagnostic_endpoint_errors'] ?? []),
            ]);

            return $summary;
        });
    }

    /**
     * Fetch node performance samples for CPU, load, memory, and network graphs.
     *
     * @return array<string, mixed>
     */
    public function nodePerformance(ProxmoxServer $server, ?string $nodeName = null, string $timeframe = 'hour'): array
    {
        return $this->runWithOperation($server, 'node-performance', function () use ($server, $nodeName, $timeframe): array {
            $errors = [];
            $nodes = collect($this->getData($server, '/nodes') ?? []);
            $selectedNode = $nodeName ?: ($nodes->first()['node'] ?? $nodes->first()['name'] ?? null);

            if (! $selectedNode) {
                throw new RuntimeException('No Proxmox node was available for performance metrics.');
            }

            $rrdData = $this->getOptionalData(
                $server,
                "/nodes/{$selectedNode}/rrddata",
                $errors,
                [],
                ['timeframe' => $timeframe, 'cf' => 'AVERAGE'],
            );
            $status = $this->getOptionalData($server, "/nodes/{$selectedNode}/status", $errors, []);

            $samples = collect($rrdData)
                ->filter(fn (mixed $sample): bool => is_array($sample) && isset($sample['time']))
                ->sortBy('time')
                ->values()
                ->all();

            $this->logInfo('Proxmox node performance loaded', $server, [
                'node' => $selectedNode,
                'timeframe' => $timeframe,
                'sample_count' => count($samples),
                'error_count' => count($errors),
            ]);

            return [
                'node' => $selectedNode,
                'nodes' => $nodes->map(fn (array $node): string => (string) ($node['node'] ?? $node['name'] ?? ''))->filter()->values()->all(),
                'timeframe' => $timeframe,
                'samples' => $samples,
                'latest' => $this->latestPerformanceValues($samples, is_array($status) ? $status : []),
                'errors' => $errors,
                'fetched_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Fetch VM performance samples for customer monitoring graphs.
     *
     * @return array<string, mixed>
     */
    public function qemuPerformance(ProxmoxServer $server, string $node, int $vmid, string $timeframe = 'hour'): array
    {
        return $this->runWithOperation($server, 'qemu-performance', function () use ($server, $node, $vmid, $timeframe): array {
            $errors = [];
            $rrdData = $this->getOptionalData(
                $server,
                "/nodes/{$node}/qemu/{$vmid}/rrddata",
                $errors,
                [],
                ['timeframe' => $timeframe, 'cf' => 'AVERAGE'],
            );
            $status = $this->getOptionalData($server, "/nodes/{$node}/qemu/{$vmid}/status/current", $errors, []);

            $samples = collect($rrdData)
                ->filter(fn (mixed $sample): bool => is_array($sample) && isset($sample['time']))
                ->sortBy('time')
                ->values()
                ->all();

            $this->logInfo('Proxmox VM performance loaded', $server, [
                'node' => $node,
                'vmid' => $vmid,
                'timeframe' => $timeframe,
                'sample_count' => count($samples),
                'error_count' => count($errors),
            ]);

            return [
                'node' => $node,
                'vmid' => $vmid,
                'timeframe' => $timeframe,
                'samples' => $samples,
                'latest' => $this->latestVmPerformanceValues($samples, is_array($status) ? $status : []),
                'status' => is_array($status) ? $status : [],
                'errors' => $errors,
                'fetched_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Create a short-lived QEMU console proxy session for noVNC.
     *
     * @return array{port: int, ticket: string, headers: array<string, string>, raw: array<string, mixed>}
     */
    public function qemuConsoleSession(ProxmoxServer $server, string $node, int $vmid): array
    {
        return $this->runWithOperation($server, 'qemu-console-session', function () use ($server, $node, $vmid): array {
            $payload = $this->request($server)
                ->asForm()
                ->post("/nodes/{$node}/qemu/{$vmid}/vncproxy", [
                    'websocket' => 1,
                ])
                ->throw()
                ->json('data');

            if (! is_array($payload) || ! isset($payload['port'], $payload['ticket'])) {
                throw new RuntimeException('Proxmox did not return console proxy details.');
            }

            return [
                'port' => (int) $payload['port'],
                'ticket' => (string) $payload['ticket'],
                'headers' => $this->websocketAuthHeaders($server),
                'raw' => $payload,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function vmCreationOptions(ProxmoxServer $server): array
    {
        $errors = [];
        $nodes = collect($this->getData($server, '/nodes') ?? [])
            ->filter(fn (array $node): bool => ($node['status'] ?? null) === 'online')
            ->values();
        $nextId = $this->getOptionalData($server, '/cluster/nextid', $errors);

        return [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'datacenter' => $server->datacenter,
                'cluster_name' => $server->cluster_name,
            ],
            'next_vmid' => $nextId,
            'nodes' => $nodes->map(fn (array $node): array => [
                'name' => $node['node'] ?? $node['name'] ?? null,
                'display' => ($node['node'] ?? $node['name'] ?? 'node').' (CPU '.round((float) ($node['cpu'] ?? 0) * 100).'%, RAM '.$this->humanBytes((int) ($node['mem'] ?? 0)).' / '.$this->humanBytes((int) ($node['maxmem'] ?? 0)).')',
                'raw' => $node,
            ])->filter(fn (array $node): bool => filled($node['name']))->values()->all(),
            'iso_files' => $this->isoFiles($server, $nodes->all(), $errors),
            'disk_storages' => $this->diskStorages($server, $nodes->all(), $errors),
            'bridges' => $this->networkBridges($server, $nodes->all(), $errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createQemuVm(ProxmoxServer $server, array $options): array
    {
        $node = $options['node'];
        $vmid = (int) $options['vmid'];
        $storage = $options['storage'];
        $isoVolume = $options['iso_volume'];
        $bridge = $options['network_bridge'] ?? 'vmbr0';
        $diskGb = (int) $options['disk_gb'];

        $payload = [
            'vmid' => $vmid,
            'name' => $options['name'],
            'cores' => (int) $options['cpu_cores'],
            'memory' => (int) $options['ram_gb'] * 1024,
            'ostype' => $options['ostype'] ?? 'l26',
            'scsihw' => 'virtio-scsi-pci',
            'scsi0' => "{$storage}:{$diskGb}",
            'ide2' => "{$isoVolume},media=cdrom",
            'net0' => "virtio,bridge={$bridge}",
            'boot' => 'order=ide2;scsi0;net0',
            'agent' => 1,
            'onboot' => ! empty($options['onboot']) ? 1 : 0,
            'description' => $options['description'] ?? null,
        ];

        $payload = array_filter($payload, fn (mixed $value): bool => $value !== null && $value !== '');
        $taskId = $this->request($server)->asForm()->post("/nodes/{$node}/qemu", $payload)->throw()->json('data');

        if (! empty($options['start_after_create'])) {
            $this->request($server)->asForm()->post("/nodes/{$node}/qemu/{$vmid}/status/start")->throw();
        }

        return [
            'task_id' => $taskId,
            'payload' => $payload,
            'started' => ! empty($options['start_after_create']),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function cloneCloudTemplate(ProxmoxServer $server, array $options): array
    {
        $node = $options['node'];
        $templateVmid = (int) $options['template_vmid'];
        $newid = (int) $options['newid'];

        $payload = array_filter([
            'newid' => $newid,
            'name' => $options['name'],
            'full' => 1,
            'storage' => $options['storage'] ?? null,
            'description' => $options['description'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $taskId = $this->request($server)
            ->asForm()
            ->post("/nodes/{$node}/qemu/{$templateVmid}/clone", $payload)
            ->throw()
            ->json('data');

        return [
            'task_id' => $taskId,
            'payload' => $payload,
            'template_vmid' => $templateVmid,
            'newid' => $newid,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function configureCloudInit(ProxmoxServer $server, array $options): array
    {
        $node = $options['node'];
        $vmid = (int) $options['vmid'];

        $payload = array_filter([
            'cores' => (int) $options['cpu_cores'],
            'memory' => (int) $options['ram_gb'] * 1024,
            'ciuser' => $options['login_username'] ?? null,
            'cipassword' => $options['login_password'] ?? null,
            'sshkeys' => $options['ssh_public_key'] ?? null,
            'ipconfig0' => $options['ipconfig0'] ?? null,
            'nameserver' => $options['nameserver'] ?? null,
            'cicustom' => $options['cicustom'] ?? null,
            'onboot' => ! empty($options['onboot']) ? 1 : 0,
            'agent' => 1,
            'description' => $options['description'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $taskId = $this->request($server)
            ->asForm()
            ->put("/nodes/{$node}/qemu/{$vmid}/config", $payload)
            ->throw()
            ->json('data');

        return [
            'task_id' => $taskId,
            'payload' => array_diff_key($payload, ['cipassword' => true, 'sshkeys' => true]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function regenerateCloudInit(ProxmoxServer $server, string $node, int $vmid): array
    {
        $taskId = $this->request($server)
            ->asForm()
            ->put("/nodes/{$node}/qemu/{$vmid}/cloudinit")
            ->throw()
            ->json('data');

        return ['task_id' => $taskId];
    }

    /**
     * @return array<string, mixed>
     */
    public function vmConfig(ProxmoxServer $server, string $node, int $vmid): array
    {
        return $this->getData($server, "/nodes/{$node}/qemu/{$vmid}/config") ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function vmConfigOrNull(ProxmoxServer $server, string $node, int $vmid): ?array
    {
        try {
            return $this->vmConfig($server, $node, $vmid);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function resizeDisk(ProxmoxServer $server, string $node, int $vmid, string $disk, int $sizeGb): array
    {
        $payload = ['disk' => $disk, 'size' => $sizeGb.'G'];
        $taskId = $this->request($server)
            ->asForm()
            ->put("/nodes/{$node}/qemu/{$vmid}/resize", $payload)
            ->throw()
            ->json('data');

        return ['task_id' => $taskId, 'payload' => $payload];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function updateVmHardware(ProxmoxServer $server, string $node, int $vmid, array $options): array
    {
        $payload = array_filter([
            'cores' => isset($options['cpu_cores']) ? (int) $options['cpu_cores'] : null,
            'memory' => isset($options['ram_gb']) ? (int) $options['ram_gb'] * 1024 : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $taskId = $this->request($server)
            ->asForm()
            ->put("/nodes/{$node}/qemu/{$vmid}/config", $payload)
            ->throw()
            ->json('data');

        return ['task_id' => $taskId, 'payload' => $payload];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function attachDisk(ProxmoxServer $server, string $node, int $vmid, array $options): array
    {
        $device = (string) $options['device'];
        $storage = (string) $options['storage'];
        $sizeGb = (int) $options['size_gb'];
        $payload = [$device => "{$storage}:{$sizeGb}"];
        $taskId = $this->request($server)
            ->asForm()
            ->put("/nodes/{$node}/qemu/{$vmid}/config", $payload)
            ->throw()
            ->json('data');

        return ['task_id' => $taskId, 'payload' => $payload];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function nextScsiDiskDevice(array $config): string
    {
        for ($slot = 1; $slot <= 30; $slot++) {
            $device = 'scsi'.$slot;

            if (! array_key_exists($device, $config)) {
                return $device;
            }
        }

        throw new RuntimeException('No free SCSI disk slot is available for this VM.');
    }

    /**
     * @return array<string, mixed>
     */
    public function startVm(ProxmoxServer $server, string $node, int $vmid): array
    {
        $taskId = $this->request($server)
            ->asForm()
            ->post("/nodes/{$node}/qemu/{$vmid}/status/start")
            ->throw()
            ->json('data');

        return ['task_id' => $taskId];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function vmStatus(ProxmoxServer $server, string $node, int $vmid): ?array
    {
        try {
            return $this->getData($server, "/nodes/{$node}/qemu/{$vmid}/status/current");
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function shutdownVm(ProxmoxServer $server, string $node, int $vmid, bool $forceStopFallback = true): array
    {
        try {
            $taskId = $this->request($server)
                ->asForm()
                ->post("/nodes/{$node}/qemu/{$vmid}/status/shutdown")
                ->throw()
                ->json('data');

            return ['task_id' => $taskId];
        } catch (RequestException $exception) {
            if (! $forceStopFallback) {
                throw $exception;
            }

            $taskId = $this->request($server)
                ->asForm()
                ->post("/nodes/{$node}/qemu/{$vmid}/status/stop")
                ->throw()
                ->json('data');

            return ['task_id' => $taskId, 'fallback' => 'force_stop'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function stopVm(ProxmoxServer $server, string $node, int $vmid): array
    {
        $taskId = $this->request($server)
            ->asForm()
            ->post("/nodes/{$node}/qemu/{$vmid}/status/stop")
            ->throw()
            ->json('data');

        return ['task_id' => $taskId, 'fallback' => 'force_stop'];
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteVm(ProxmoxServer $server, string $node, int $vmid, bool $purge = true): array
    {
        $payload = [
            'purge' => $purge ? 1 : 0,
            'destroy-unreferenced-disks' => 1,
        ];

        try {
            $taskId = $this->request($server)
                ->asForm()
                ->send('DELETE', "/nodes/{$node}/qemu/{$vmid}", ['form_params' => $payload])
                ->throw()
                ->json('data');
        } catch (RequestException $exception) {
            if (! $this->isUnsupportedDestroyUnreferencedDisks($exception)) {
                throw $exception;
            }

            unset($payload['destroy-unreferenced-disks']);

            $taskId = $this->request($server)
                ->asForm()
                ->send('DELETE', "/nodes/{$node}/qemu/{$vmid}", ['form_params' => $payload])
                ->throw()
                ->json('data');
        }

        return ['task_id' => $taskId, 'payload' => $payload];
    }

    private function isUnsupportedDestroyUnreferencedDisks(RequestException $exception): bool
    {
        if ($exception->response->status() >= 500) {
            return false;
        }

        $body = strtolower($exception->response->body());

        return str_contains($body, 'destroy-unreferenced-disks')
            || str_contains($body, 'unknown option')
            || str_contains($body, 'parameter verification failed');
    }

    /**
     * @return array<int, string>
     */
    public function assignedGuestIpAddresses(ProxmoxServer $server, ?string $nodeName = null): array
    {
        return $this->runWithOperation($server, 'guest-ip-inventory', function () use ($server, $nodeName): array {
            $errors = [];
            try {
                $nodes = collect($this->getData($server, '/nodes') ?? [])
                    ->filter(function (array $node) use ($nodeName): bool {
                        $current = $node['node'] ?? $node['name'] ?? null;

                        return filled($current) && ($nodeName === null || $current === $nodeName);
                    })
                    ->values()
                    ->all();
            } catch (\Throwable $exception) {
                $this->logInfo('Proxmox node inventory unavailable; using local VM IP inventory fallback', $server, [
                    'node' => $nodeName,
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);

                return $this->localAssignedGuestIpAddresses($server, $nodeName);
            }

            $addresses = [];

            try {
                foreach ($this->nodeVmInventory($server, $nodes, $errors) as $guest) {
                    $node = $guest['node'] ?? null;
                    $vmid = isset($guest['vmid']) ? (int) $guest['vmid'] : null;
                    $type = $guest['type'] ?? 'qemu';

                    if (! $node || ! $vmid) {
                        continue;
                    }

                    if ($type === 'qemu') {
                        $interfaces = $this->qemuGuestAgentNetworkInterfaces($server, $node, $vmid, $errors);
                        $addresses = array_merge($addresses, $this->extractIpAddresses($interfaces));
                    }

                    $configPath = $type === 'lxc'
                        ? "/nodes/{$node}/lxc/{$vmid}/config"
                        : "/nodes/{$node}/qemu/{$vmid}/config";

                    $config = $this->getOptionalData($server, $configPath, $errors, []);
                    $addresses = array_merge($addresses, $this->extractIpAddresses($config));
                }
            } catch (\Throwable $exception) {
                $this->logInfo('Proxmox guest IP inventory unavailable; using local VM IP inventory fallback', $server, [
                    'node' => $nodeName,
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);

                return $this->localAssignedGuestIpAddresses($server, $nodeName);
            }

            $addresses = array_merge($addresses, $this->localAssignedGuestIpAddresses($server, $nodeName));

            return collect($addresses)
                ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * @return array<int, int>
     */
    public function assignedGuestVmids(ProxmoxServer $server, ?string $nodeName = null): array
    {
        return $this->runWithOperation($server, 'guest-vmid-inventory', function () use ($server, $nodeName): array {
            $errors = [];
            $nodes = collect($this->getData($server, '/nodes') ?? [])
                ->filter(function (array $node) use ($nodeName): bool {
                    $current = $node['node'] ?? $node['name'] ?? null;

                    return filled($current) && ($nodeName === null || $current === $nodeName);
                })
                ->values()
                ->all();

            return collect($this->nodeVmInventory($server, $nodes, $errors))
                ->pluck('vmid')
                ->filter(fn (mixed $vmid): bool => is_numeric($vmid))
                ->map(fn (mixed $vmid): int => (int) $vmid)
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * @return array<int, string>
     */
    private function extractIpAddresses(mixed $value): array
    {
        if (is_array($value)) {
            $addresses = [];

            foreach ($value as $item) {
                $addresses = array_merge($addresses, $this->extractIpAddresses($item));
            }

            return $addresses;
        }

        if (! is_string($value)) {
            return [];
        }

        preg_match_all("/(?<![\d.])(?:\d{1,3}\.){3}\d{1,3}(?![\d.])/", $value, $matches);

        return $matches[0] ?? [];
    }

    /**
     *  array<string, mixed>
     */
    public function nextVmid(ProxmoxServer $server): array
    {
        return ['vmid' => (int) $this->getData($server, '/cluster/nextid')];
    }

    /**
     * @return array<string, mixed>
     */
    public function waitForTask(ProxmoxServer $server, string $node, string $upid, int $timeoutSeconds = 300): array
    {
        $deadline = now()->addSeconds($timeoutSeconds);
        $lastStatus = null;

        while (now()->lessThanOrEqualTo($deadline)) {
            $lastStatus = $this->getData($server, "/nodes/{$node}/tasks/{$upid}/status") ?? [];

            if (($lastStatus['status'] ?? null) === 'stopped') {
                if (($lastStatus['exitstatus'] ?? 'OK') !== 'OK') {
                    throw new RuntimeException('Proxmox task failed: '.($lastStatus['exitstatus'] ?? 'unknown error'));
                }

                return $lastStatus;
            }

            sleep(2);
        }

        throw new RuntimeException('Timed out waiting for Proxmox task '.$upid.'. Last status: '.json_encode($lastStatus));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function backupStorages(ProxmoxServer $server, string $node): array
    {
        $errors = [];

        return collect($this->nodeStoragesWithContent($server, $node, 'backup', $errors))
            ->map(fn (array $storage): array => [
                'storage' => $storage['storage'],
                'type' => $storage['type'] ?? null,
                'avail' => $storage['avail'] ?? null,
                'display' => $node.' / '.$storage['storage'].' ('.$this->humanBytes((int) ($storage['avail'] ?? 0)).' free)',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function startBackup(ProxmoxServer $server, array $options): array
    {
        $node = $options['node'];
        $payload = array_filter([
            'vmid' => (string) $options['vmid'],
            'storage' => $options['storage'] ?? null,
            'mode' => $options['mode'] ?? 'snapshot',
            'compress' => $options['compress'] ?? 'zstd',
            'remove' => 0,
            'notes-template' => $options['notes_template'] ?? 'Aviato panel backup - {{guestname}} - {{vmid}}',
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $taskId = $this->request($server)
            ->asForm()
            ->post("/nodes/{$node}/vzdump", $payload)
            ->throw()
            ->json('data');

        return ['task_id' => $taskId, 'payload' => $payload];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function backupFilesForVm(ProxmoxServer $server, string $node, int $vmid, ?string $storage = null): array
    {
        $errors = [];
        $storages = $storage
            ? [['storage' => $storage]]
            : $this->nodeStoragesWithContent($server, $node, 'backup', $errors);

        $items = [];

        foreach ($storages as $store) {
            $storageId = $store['storage'] ?? null;
            if (! $storageId) {
                continue;
            }

            $contents = $this->getOptionalData($server, "/nodes/{$node}/storage/{$storageId}/content", $errors, [], ['content' => 'backup']);

            foreach ($contents as $content) {
                $volid = (string) ($content['volid'] ?? $content['volume'] ?? '');
                $name = basename($volid ?: (string) ($content['filename'] ?? ''));

                if (! str_contains($name, 'vzdump-qemu-'.$vmid.'-')) {
                    continue;
                }

                $content['node'] = $node;
                $content['storage'] = $storageId;
                $content['volid'] = $volid ?: ($content['volume'] ?? null);
                $content['filename'] = $name;
                $items[] = $content;
            }
        }

        return collect($items)
            ->sortByDesc(fn (array $item): int => (int) ($item['ctime'] ?? $item['mtime'] ?? 0))
            ->values()
            ->all();
    }

    public function deleteBackupFile(ProxmoxServer $server, string $node, string $storage, string $volid): void
    {
        $this->request($server)
            ->delete("/nodes/{$node}/storage/{$storage}/content/".rawurlencode($volid))
            ->throw();
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $resources
     * @param  array<int, array<string, mixed>>  $storage
     * @param  array<int, array<string, mixed>>  $backups
     * @return array<string, int>
     */
    protected function counts(array $nodes, array $resources, array $storage, array $backups): array
    {
        $nodeCollection = collect($nodes);
        $vmCollection = collect($resources);

        return [
            'nodes' => $nodeCollection->count(),
            'online_nodes' => $nodeCollection->where('status', 'online')->count(),
            'offline_nodes' => $nodeCollection->reject(fn (array $node): bool => ($node['status'] ?? null) === 'online')->count(),
            'virtual_machines' => $vmCollection->count(),
            'running_virtual_machines' => $vmCollection->where('status', 'running')->count(),
            'offline_virtual_machines' => $vmCollection->reject(fn (array $vm): bool => ($vm['status'] ?? null) === 'running')->count(),
            'storage' => count($storage),
            'backups' => count($backups),
        ];
    }

    protected function getData(ProxmoxServer $server, string $path, array $query = []): mixed
    {
        $startedAt = microtime(true);

        $this->logInfo('Proxmox API request starting', $server, [
            'method' => 'GET',
            'path' => $path,
            'query' => $query,
        ]);

        try {
            $response = $this->request($server)->get($path, $query);
            $durationMs = $this->durationMs($startedAt);

            $this->logInfo('Proxmox API response received', $server, [
                'method' => 'GET',
                'path' => $path,
                'query' => $query,
                'status' => $response->status(),
                'effective_uri' => (string) $response->effectiveUri(),
                'duration_ms' => $durationMs,
                'content_type' => $response->header('content-type'),
                'body_preview' => $this->bodyPreview($response->body()),
            ]);

            return $response->throw()->json('data');
        } catch (ConnectionException $exception) {
            $this->logError('Proxmox API connection failed', $server, [
                'method' => 'GET',
                'path' => $path,
                'query' => $query,
                'duration_ms' => $this->durationMs($startedAt),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (RequestException $exception) {
            $this->logHttpFailure($server, 'GET '.$path, $exception, [
                'query' => $query,
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    protected function getOptionalData(ProxmoxServer $server, string $path, array &$errors, mixed $default = null, array $query = []): mixed
    {
        try {
            return $this->getData($server, $path, $query) ?? $default;
        } catch (RequestException $exception) {
            $errors[$path] = 'HTTP '.$exception->response->status();
            $this->logHttpFailure($server, 'optional endpoint '.$path, $exception, [
                'optional' => true,
                'query' => $query,
                'using_default' => $default,
            ]);

            return $default;
        }
    }

    /**
     * @param  array<string, string>  $errors
     * @return array<int, mixed>
     */
    private function qemuGuestAgentNetworkInterfaces(ProxmoxServer $server, string $node, int $vmid, array &$errors): array
    {
        $path = "/nodes/{$node}/qemu/{$vmid}/agent/network-get-interfaces";
        $startedAt = microtime(true);

        $this->logInfo('Proxmox API request starting', $server, [
            'method' => 'GET',
            'path' => $path,
            'query' => [],
        ]);

        try {
            $response = $this->request($server)->get($path);
            $durationMs = $this->durationMs($startedAt);

            $this->logInfo('Proxmox API response received', $server, [
                'method' => 'GET',
                'path' => $path,
                'query' => [],
                'status' => $response->status(),
                'effective_uri' => (string) $response->effectiveUri(),
                'duration_ms' => $durationMs,
                'content_type' => $response->header('content-type'),
                'body_preview' => $this->bodyPreview($response->body()),
            ]);

            if ($response->failed() && $this->isGuestAgentUnavailableResponse($response->status(), $response->body())) {
                $errors[$path] = 'guest-agent-unavailable';
                $this->logInfo('Proxmox QEMU guest agent unavailable; using VM config fallback', $server, [
                    'node' => $node,
                    'vmid' => $vmid,
                    'status' => $response->status(),
                    'duration_ms' => $durationMs,
                ]);

                return [];
            }

            return $response->throw()->json('data') ?? [];
        } catch (ConnectionException $exception) {
            $errors[$path] = 'connection-failed';
            $this->logError('Proxmox API connection failed', $server, [
                'method' => 'GET',
                'path' => $path,
                'query' => [],
                'duration_ms' => $this->durationMs($startedAt),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return [];
        } catch (RequestException $exception) {
            $errors[$path] = 'HTTP '.$exception->response->status();
            $this->logHttpFailure($server, 'optional endpoint '.$path, $exception, [
                'optional' => true,
                'query' => [],
                'using_default' => [],
            ]);

            return [];
        }
    }

    private function isGuestAgentUnavailableResponse(int $status, string $body): bool
    {
        return $status === 500 && str_contains(strtolower($body), 'qemu guest agent is not running');
    }

    /**
     * @return array<int, string>
     */
    private function localAssignedGuestIpAddresses(ProxmoxServer $server, ?string $nodeName = null): array
    {
        return VirtualMachine::query()
            ->where('proxmox_server_id', $server->id)
            ->when($nodeName !== null, fn ($query) => $query->where('node', $nodeName))
            ->whereNotNull('ip_address')
            ->whereNull('deleted_at')
            ->where('status', '!=', VirtualMachine::STATUS_DELETED)
            ->pluck('ip_address')
            ->filter(fn (mixed $address): bool => is_string($address) && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    protected function vmInventory(ProxmoxServer $server, array $nodes, array &$errors): array
    {
        $resources = $this->getOptionalData($server, '/cluster/resources', $errors, [], ['type' => 'vm']);

        if (! empty($resources)) {
            $this->logInfo('Proxmox VM inventory loaded from cluster resources', $server, [
                'vm_count' => count($resources),
            ]);

            return $resources;
        }

        $this->logInfo('Proxmox cluster VM inventory was empty; trying per-node VM endpoints', $server, [
            'node_count' => count($nodes),
        ]);

        return $this->nodeVmInventory($server, $nodes, $errors);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    protected function nodeVmInventory(ProxmoxServer $server, array $nodes, array &$errors): array
    {
        $items = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'] ?? $node['name'] ?? null;

            if (! $nodeName) {
                continue;
            }

            foreach ($this->getOptionalData($server, "/nodes/{$nodeName}/qemu", $errors, []) as $vm) {
                $vm['node'] = $nodeName;
                $vm['type'] = $vm['type'] ?? 'qemu';
                $vm['id'] = $vm['id'] ?? 'qemu/'.$vm['vmid'];
                $items[] = $vm;
            }

            foreach ($this->getOptionalData($server, "/nodes/{$nodeName}/lxc", $errors, []) as $container) {
                $container['node'] = $nodeName;
                $container['type'] = $container['type'] ?? 'lxc';
                $container['id'] = $container['id'] ?? 'lxc/'.$container['vmid'];
                $items[] = $container;
            }
        }

        $items = collect($items)
            ->unique(fn (array $item): string => (string) ($item['id'] ?? $item['node'].'/'.$item['vmid']))
            ->values()
            ->all();

        $this->logInfo('Proxmox per-node VM inventory loaded', $server, [
            'vm_count' => count($items),
        ]);

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    protected function storageInventory(ProxmoxServer $server, array $nodes, array &$errors): array
    {
        $inventory = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'] ?? $node['name'] ?? null;

            if (! $nodeName) {
                continue;
            }

            $storages = $this->getOptionalData($server, "/nodes/{$nodeName}/storage", $errors, []);

            foreach ($storages as $storage) {
                $storage['node'] = $nodeName;
                $inventory[] = $storage;
            }
        }

        if (! empty($inventory)) {
            return $inventory;
        }

        $this->logInfo('Proxmox node storage inventory was empty; trying datacenter storage endpoint', $server, [
            'node_count' => count($nodes),
        ]);

        $storages = $this->getOptionalData($server, '/storage', $errors, []);
        $fallbackNode = count($nodes) === 1 ? ($nodes[0]['node'] ?? $nodes[0]['name'] ?? null) : null;

        foreach ($storages as $storage) {
            $storage['node'] = $fallbackNode;
            $storage['active'] = $storage['active'] ?? null;
            $inventory[] = $storage;
        }

        $this->logInfo('Proxmox datacenter storage inventory loaded', $server, [
            'storage_count' => count($inventory),
        ]);

        return $inventory;
    }

    /**
     * @param  array<int, array<string, mixed>>  $storage
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    protected function backupInventory(ProxmoxServer $server, array $storage, array &$errors): array
    {
        $backups = [];

        foreach ($storage as $store) {
            $node = $store['node'] ?? null;
            $storageId = $store['storage'] ?? null;
            $content = (string) ($store['content'] ?? '');

            if (! $node || ! $storageId || ! str_contains($content, 'backup')) {
                continue;
            }

            $items = $this->getOptionalData($server, "/nodes/{$node}/storage/{$storageId}/content", $errors, [], ['content' => 'backup']);

            foreach ($items as $item) {
                $item['node'] = $node;
                $item['storage'] = $storageId;
                $backups[] = $item;
            }
        }

        return $backups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function isoFiles(ProxmoxServer $server, array $nodes, array &$errors): array
    {
        $items = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'] ?? $node['name'] ?? null;
            if (! $nodeName) {
                continue;
            }

            foreach ($this->nodeStoragesWithContent($server, $nodeName, 'iso', $errors) as $storage) {
                $storageId = $storage['storage'];
                $contents = $this->getOptionalData($server, "/nodes/{$nodeName}/storage/{$storageId}/content", $errors, [], ['content' => 'iso']);

                foreach ($contents as $content) {
                    if (($content['content'] ?? null) !== 'iso') {
                        continue;
                    }

                    $volume = $content['volid'] ?? $content['volume'] ?? null;
                    if (! $volume) {
                        continue;
                    }

                    $items[] = [
                        'node' => $nodeName,
                        'storage' => $storageId,
                        'volume' => $volume,
                        'name' => basename((string) $volume),
                        'size' => $content['size'] ?? null,
                        'display' => $nodeName.' / '.$storageId.' / '.basename((string) $volume),
                    ];
                }
            }
        }

        return collect($items)->unique(fn (array $item): string => $item['node'].'|'.$item['volume'])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function diskStorages(ProxmoxServer $server, array $nodes, array &$errors): array
    {
        $items = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'] ?? $node['name'] ?? null;
            if (! $nodeName) {
                continue;
            }

            foreach ($this->nodeStoragesWithContent($server, $nodeName, 'images', $errors) as $storage) {
                $items[] = [
                    'node' => $nodeName,
                    'storage' => $storage['storage'],
                    'type' => $storage['type'] ?? null,
                    'avail' => $storage['avail'] ?? null,
                    'display' => $nodeName.' / '.$storage['storage'].' ('.$this->humanBytes((int) ($storage['avail'] ?? 0)).' free)',
                ];
            }
        }

        return collect($items)->unique(fn (array $item): string => $item['node'].'|'.$item['storage'])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function networkBridges(ProxmoxServer $server, array $nodes, array &$errors): array
    {
        $items = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'] ?? $node['name'] ?? null;
            if (! $nodeName) {
                continue;
            }

            $networks = $this->getOptionalData($server, "/nodes/{$nodeName}/network", $errors, []);
            foreach ($networks as $network) {
                if (($network['type'] ?? null) !== 'bridge') {
                    continue;
                }

                $iface = $network['iface'] ?? null;
                if (! $iface) {
                    continue;
                }

                $items[] = [
                    'node' => $nodeName,
                    'iface' => $iface,
                    'active' => (bool) ($network['active'] ?? false),
                    'display' => $nodeName.' / '.$iface.(! empty($network['active']) ? ' (active)' : ''),
                ];
            }
        }

        return collect($items)->unique(fn (array $item): string => $item['node'].'|'.$item['iface'])->values()->all();
    }

    /**
     * @param  array<string, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function nodeStoragesWithContent(ProxmoxServer $server, string $nodeName, string $contentType, array &$errors): array
    {
        $storages = $this->getOptionalData($server, "/nodes/{$nodeName}/storage", $errors, []);

        return collect($storages)
            ->filter(function (array $storage) use ($contentType): bool {
                $content = ','.(string) ($storage['content'] ?? '').',';

                return str_contains($content, ','.$contentType.',') && ! ($storage['disable'] ?? false);
            })
            ->values()
            ->all();
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function latestPerformanceValues(array $samples, array $status): array
    {
        $latestSample = end($samples) ?: [];
        $cpu = $latestSample['cpu'] ?? $status['cpu'] ?? null;
        $load = $latestSample['loadavg'] ?? $latestSample['load'] ?? $status['loadavg'][0] ?? null;
        $mem = $latestSample['mem'] ?? $status['memory']['used'] ?? null;
        $maxMem = $latestSample['maxmem'] ?? $status['memory']['total'] ?? null;
        $netIn = $latestSample['netin'] ?? null;
        $netOut = $latestSample['netout'] ?? null;

        return [
            'cpu_percent' => is_numeric($cpu) ? round((float) $cpu * 100, 2) : null,
            'load' => is_numeric($load) ? round((float) $load, 2) : null,
            'memory_percent' => is_numeric($mem) && is_numeric($maxMem) && (float) $maxMem > 0
                ? round(((float) $mem / (float) $maxMem) * 100, 2)
                : null,
            'memory_used' => is_numeric($mem) ? $this->humanBytes((int) $mem) : null,
            'memory_total' => is_numeric($maxMem) ? $this->humanBytes((int) $maxMem) : null,
            'netin_bytes_per_second' => is_numeric($netIn) ? round((float) $netIn, 2) : null,
            'netout_bytes_per_second' => is_numeric($netOut) ? round((float) $netOut, 2) : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function latestVmPerformanceValues(array $samples, array $status): array
    {
        $latestSample = end($samples) ?: [];
        $cpu = $latestSample['cpu'] ?? $status['cpu'] ?? null;
        $mem = $latestSample['mem'] ?? $status['mem'] ?? null;
        $maxMem = $latestSample['maxmem'] ?? $status['maxmem'] ?? null;
        $disk = $latestSample['disk'] ?? $status['disk'] ?? null;
        $maxDisk = $latestSample['maxdisk'] ?? $status['maxdisk'] ?? null;
        $netIn = $latestSample['netin'] ?? $status['netin'] ?? null;
        $netOut = $latestSample['netout'] ?? $status['netout'] ?? null;
        $diskRead = $latestSample['diskread'] ?? $status['diskread'] ?? null;
        $diskWrite = $latestSample['diskwrite'] ?? $status['diskwrite'] ?? null;
        $uptime = $status['uptime'] ?? null;

        return [
            'status' => $status['status'] ?? null,
            'cpu_percent' => is_numeric($cpu) ? round((float) $cpu * 100, 2) : null,
            'memory_percent' => is_numeric($mem) && is_numeric($maxMem) && (float) $maxMem > 0
                ? round(((float) $mem / (float) $maxMem) * 100, 2)
                : null,
            'memory_used' => is_numeric($mem) ? $this->humanBytes((int) $mem) : null,
            'memory_total' => is_numeric($maxMem) ? $this->humanBytes((int) $maxMem) : null,
            'disk_percent' => is_numeric($disk) && is_numeric($maxDisk) && (float) $maxDisk > 0
                ? round(((float) $disk / (float) $maxDisk) * 100, 2)
                : null,
            'disk_used' => is_numeric($disk) ? $this->humanBytes((int) $disk) : null,
            'disk_total' => is_numeric($maxDisk) ? $this->humanBytes((int) $maxDisk) : null,
            'netin_bytes_per_second' => is_numeric($netIn) ? round((float) $netIn, 2) : null,
            'netout_bytes_per_second' => is_numeric($netOut) ? round((float) $netOut, 2) : null,
            'diskread_bytes_per_second' => is_numeric($diskRead) ? round((float) $diskRead, 2) : null,
            'diskwrite_bytes_per_second' => is_numeric($diskWrite) ? round((float) $diskWrite, 2) : null,
            'uptime_seconds' => is_numeric($uptime) ? (int) $uptime : null,
        ];
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runWithOperation(ProxmoxServer $server, string $operation, callable $callback): mixed
    {
        $previousOperationId = $this->operationId;
        $this->operationId = $previousOperationId ?? bin2hex(random_bytes(6));
        $startedAt = microtime(true);

        $this->logInfo('Proxmox operation starting', $server, [
            'operation' => $operation,
            'desired_state_snapshot' => $server->desiredStateSnapshot(),
            'stored_connection_status' => $server->connection_status,
            'stored_sync_status' => $server->sync_status,
        ]);

        try {
            $result = $callback();

            $this->logInfo('Proxmox operation completed', $server, [
                'operation' => $operation,
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $result;
        } catch (\Throwable $exception) {
            $this->logError('Proxmox operation failed', $server, [
                'operation' => $operation,
                'duration_ms' => $this->durationMs($startedAt),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
            ]);

            throw $exception;
        } finally {
            $this->operationId = $previousOperationId;
        }
    }

    /** @param array<string, mixed> $context */
    private function logInfo(string $message, ProxmoxServer $server, array $context = []): void
    {
        Log::info($message, $this->logContext($server, $context));
    }

    /** @param array<string, mixed> $context */
    private function logError(string $message, ProxmoxServer $server, array $context = []): void
    {
        Log::error($message, $this->logContext($server, $context));
    }

    /** @param array<string, mixed> $context */
    private function logHttpFailure(ProxmoxServer $server, string $step, RequestException $exception, array $context = []): void
    {
        $response = $exception->response;

        $this->logError('Proxmox HTTP request failed', $server, array_merge($context, [
            'step' => $step,
            'status' => $response->status(),
            'effective_uri' => (string) $response->effectiveUri(),
            'reason' => $response->reason(),
            'content_type' => $response->header('content-type'),
            'body_preview' => $this->bodyPreview($response->body()),
            'exception_message' => $exception->getMessage(),
        ]));
    }

    private function logConnectionDiagnostics(ProxmoxServer $server, \Throwable $exception): void
    {
        $diagnostics = [
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'dns_lookup' => gethostbyname($server->host),
            'target_host' => $server->host,
            'target_port' => (int) $server->port,
        ];

        $socketStartedAt = microtime(true);
        $socketErrorNumber = 0;
        $socketError = '';
        $socket = @fsockopen($server->host, (int) $server->port, $socketErrorNumber, $socketError, 5);
        $diagnostics['tcp_probe_duration_ms'] = $this->durationMs($socketStartedAt);
        $diagnostics['tcp_probe_connected'] = is_resource($socket);
        $diagnostics['tcp_probe_error_number'] = $socketErrorNumber ?: null;
        $diagnostics['tcp_probe_error'] = $socketError ?: null;

        if (is_resource($socket)) {
            fclose($socket);
        }

        $this->logError('Proxmox connection diagnostics', $server, $diagnostics);
    }

    /** @param array<string, mixed> $context */
    private function logContext(ProxmoxServer $server, array $context = []): array
    {
        return array_merge([
            'operation_id' => $this->operationId,
            'server_id' => $server->id,
            'server_name' => $server->name,
            'host' => $server->host,
            'port' => (int) $server->port,
            'base_url' => $server->baseUrl(),
            'verify_tls' => $server->verify_tls,
            'username' => $server->proxmoxUser(),
            'auth_method' => $server->usesApiToken() ? 'api_token' : 'ticket',
            'api_token_id' => $server->usesApiToken() ? $this->maskedTokenId($server) : null,
        ], $this->sanitizeLogContext($context));
    }

    private function maskedTokenId(ProxmoxServer $server): ?string
    {
        $tokenId = $server->finalApiTokenId();

        if (! $tokenId) {
            return null;
        }

        return strlen($tokenId) <= 6 ? '******' : substr($tokenId, 0, 3).'...'.substr($tokenId, -3);
    }

    /** @param array<string, mixed> $context */
    private function sanitizeLogContext(array $context): array
    {
        $sensitiveKeys = ['password', 'api_token_secret', 'Authorization', 'authorization', 'ticket', 'CSRFPreventionToken', 'PVEAuthCookie'];

        foreach ($context as $key => $value) {
            if (in_array((string) $key, $sensitiveKeys, true)) {
                $context[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->sanitizeLogContext($value);
            }
        }

        return $context;
    }

    private function bodyPreview(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        return mb_substr(preg_replace('/\s+/', ' ', $body) ?? $body, 0, 1000);
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /** @return array<string, string> */
    protected function websocketAuthHeaders(ProxmoxServer $server): array
    {
        if ($server->usesApiToken()) {
            return ['Authorization' => $this->tokenAuthorization($server)];
        }

        if (blank($server->password)) {
            throw new RuntimeException('A password or API token is required to connect to this Proxmox server.');
        }

        $response = Http::baseUrl($server->baseUrl().'/api2/json')
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->withOptions(['verify' => $server->verify_tls])
            ->asForm()
            ->post('/access/ticket', [
                'username' => $server->proxmoxUser(),
                'password' => $server->password,
            ])
            ->throw()
            ->json('data');

        if (! is_array($response) || ! isset($response['ticket'], $response['CSRFPreventionToken'])) {
            throw new RuntimeException('Proxmox did not return websocket authentication details.');
        }

        return [
            'Cookie' => 'PVEAuthCookie='.$response['ticket'],
            'CSRFPreventionToken' => (string) $response['CSRFPreventionToken'],
        ];
    }

    protected function tokenAuthorization(ProxmoxServer $server): string
    {
        $authorization = $server->apiTokenAuthorizationHeader();

        if (! $authorization) {
            throw new RuntimeException('A complete Proxmox API token id and secret are required to connect to this Proxmox server.');
        }

        return $authorization;
    }

    protected function request(ProxmoxServer $server): PendingRequest
    {
        $this->logInfo('Preparing Proxmox HTTP client', $server, [
            'base_url' => $server->baseUrl().'/api2/json',
            'connect_timeout_seconds' => 5,
            'timeout_seconds' => 10,
            'verify_tls' => $server->verify_tls,
            'auth_method' => $server->usesApiToken() ? 'api_token' : 'ticket',
            'php_openssl_loaded' => extension_loaded('openssl'),
            'php_curl_loaded' => extension_loaded('curl'),
        ]);

        $request = Http::baseUrl($server->baseUrl().'/api2/json')
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->withOptions(['verify' => $server->verify_tls]);

        if ($server->usesApiToken()) {
            return $request->withHeaders([
                'Authorization' => $this->tokenAuthorization($server),
            ]);
        }

        return $this->authenticateWithTicket($request, $server);
    }

    protected function authenticateWithTicket(PendingRequest $request, ProxmoxServer $server): PendingRequest
    {
        if (blank($server->password)) {
            $this->logError('Proxmox ticket authentication blocked: missing password', $server);
            throw new RuntimeException('A password or API token is required to connect to this Proxmox server.');
        }

        $startedAt = microtime(true);
        $this->logInfo('Proxmox ticket authentication starting', $server, [
            'username' => $server->proxmoxUser(),
            'path' => '/access/ticket',
        ]);

        try {
            $response = $request->asForm()->post('/access/ticket', [
                'username' => $server->proxmoxUser(),
                'password' => $server->password,
            ]);

            $this->logInfo('Proxmox ticket authentication response received', $server, [
                'status' => $response->status(),
                'effective_uri' => (string) $response->effectiveUri(),
                'duration_ms' => $this->durationMs($startedAt),
                'has_json_data' => is_array($response->json('data')),
            ]);

            $ticket = $response->throw()->json('data');
        } catch (ConnectionException $exception) {
            $this->logError('Proxmox ticket authentication connection failed', $server, [
                'duration_ms' => $this->durationMs($startedAt),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
            $this->logConnectionDiagnostics($server, $exception);

            throw $exception;
        } catch (RequestException $exception) {
            $this->logHttpFailure($server, 'ticket authentication', $exception, [
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            throw $exception;
        }

        if (! isset($ticket['ticket'], $ticket['CSRFPreventionToken'])) {
            $this->logError('Proxmox ticket authentication response was missing required fields', $server, [
                'has_ticket' => isset($ticket['ticket']),
                'has_csrf_token' => isset($ticket['CSRFPreventionToken']),
                'response_keys' => is_array($ticket) ? array_keys($ticket) : [],
            ]);

            throw new RuntimeException('Proxmox did not return an authentication ticket.');
        }

        $this->logInfo('Proxmox ticket authentication succeeded', $server, [
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $request->withCookies(['PVEAuthCookie' => $ticket['ticket']], $server->host)
            ->withHeader('CSRFPreventionToken', $ticket['CSRFPreventionToken']);
    }
}

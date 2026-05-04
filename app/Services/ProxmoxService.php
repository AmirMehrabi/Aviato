<?php

namespace App\Services;

use App\Models\ProxmoxServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProxmoxService
{
    /**
     * Fetch a compact cluster summary for admin/API show pages.
     *
     * @return array<string, mixed>
     */
    public function summary(ProxmoxServer $server): array
    {
        $errors = [];

        try {
            // Treat /nodes as the connectivity/auth source of truth because scoped API tokens often allow it while denying /version.
            $nodes = $this->getData($server, '/nodes') ?? [];
            $version = $this->getOptionalData($server, '/version', $errors);
            $clusterStatus = $this->getOptionalData($server, '/cluster/status', $errors, []);
            $resources = $this->getOptionalData($server, '/cluster/resources', $errors, [], ['type' => 'vm']);
            $storage = $this->storageInventory($server, $nodes, $errors);
            $backups = $this->backupInventory($server, $storage, $errors);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to connect to the Proxmox API: '.$exception->getMessage(), previous: $exception);
        } catch (RequestException $exception) {
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
        $summary = $this->summary($server);

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

        return $summary;
    }



    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, string> $errors
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

        return $inventory;
    }

    /**
     * @param array<int, array<string, mixed>> $storage
     * @param array<string, string> $errors
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
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, array<string, mixed>> $resources
     * @param array<int, array<string, mixed>> $storage
     * @param array<int, array<string, mixed>> $backups
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

    /** @return mixed */
    protected function getData(ProxmoxServer $server, string $path, array $query = []): mixed
    {
        return $this->request($server)->get($path, $query)->throw()->json('data');
    }

    /**
     * @param array<string, string> $errors
     * @return mixed
     */
    protected function getOptionalData(ProxmoxServer $server, string $path, array &$errors, mixed $default = null, array $query = []): mixed
    {
        try {
            return $this->getData($server, $path, $query) ?? $default;
        } catch (RequestException $exception) {
            $errors[$path] = 'HTTP '.$exception->response->status();

            return $default;
        }
    }

    protected function tokenAuthorization(ProxmoxServer $server): string
    {
        $tokenId = $server->api_token_id;

        if (! str_contains($tokenId, '!')) {
            $tokenId = $server->proxmoxUser().'!'.$tokenId;
        }

        return 'PVEAPIToken='.$tokenId.'='.$server->api_token_secret;
    }

    protected function request(ProxmoxServer $server): PendingRequest
    {
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
            throw new RuntimeException('A password or API token is required to connect to this Proxmox server.');
        }

        $ticket = $request->asForm()->post('/access/ticket', [
            'username' => $server->proxmoxUser(),
            'password' => $server->password,
        ])->throw()->json('data');

        if (! isset($ticket['ticket'], $ticket['CSRFPreventionToken'])) {
            throw new RuntimeException('Proxmox did not return an authentication ticket.');
        }

        return $request->withCookies(['PVEAuthCookie' => $ticket['ticket']], $server->host)
            ->withHeader('CSRFPreventionToken', $ticket['CSRFPreventionToken']);
    }
}

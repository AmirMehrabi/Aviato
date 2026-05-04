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
            $nodes = $this->getData($server, '/nodes');
            $version = $this->getOptionalData($server, '/version', $errors);
            $clusterStatus = $this->getOptionalData($server, '/cluster/status', $errors, []);
            $resources = $this->getOptionalData($server, '/cluster/resources', $errors, [], ['type' => 'vm']);
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
            'endpoint_errors' => $errors,
            'counts' => [
                'nodes' => count($nodes),
                'virtual_machines' => count($resources),
                'running_virtual_machines' => collect($resources)->where('status', 'running')->count(),
            ],
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

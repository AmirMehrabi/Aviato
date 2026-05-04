<?php

namespace App\Services;

use App\Models\ProxmoxServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProxmoxService
{
    /**
     * Fetch a compact cluster summary for an admin/API show page.
     *
     * @return array<string, mixed>
     */
    public function summary(ProxmoxServer $server): array
    {
        try {
            $version = $this->request($server)->get('/version')->throw()->json('data');
            $nodes = $this->request($server)->get('/nodes')->throw()->json('data') ?? [];
            $clusterStatus = $this->request($server)->get('/cluster/status')->throw()->json('data') ?? [];
            $resources = $this->request($server)->get('/cluster/resources', ['type' => 'vm'])->throw()->json('data') ?? [];
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to connect to the Proxmox API: '.$exception->getMessage(), previous: $exception);
        }

        return [
            'version' => $version,
            'nodes' => $nodes,
            'cluster_status' => $clusterStatus,
            'virtual_machines' => $resources,
            'counts' => [
                'nodes' => count($nodes),
                'virtual_machines' => count($resources),
                'running_virtual_machines' => collect($resources)->where('status', 'running')->count(),
            ],
            'fetched_at' => now()->toISOString(),
        ];
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
                'Authorization' => 'PVEAPIToken='.$server->proxmoxUser().'!'.$server->api_token_id.'='.$server->api_token_secret,
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

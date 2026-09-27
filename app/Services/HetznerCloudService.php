<?php

namespace App\Services;

use App\Models\HetznerAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HetznerCloudService
{
    private const BASE_URL = 'https://api.hetzner.cloud/v1';

    public function test(HetznerAccount $account): array
    {
        return $this->get($account, '/locations');
    }

    public function locations(HetznerAccount $account): array
    {
        return $this->paginated($account, '/locations', 'locations');
    }

    public function images(HetznerAccount $account): array
    {
        return $this->paginated($account, '/images', 'images', ['type' => 'system']);
    }

    public function serverTypes(HetznerAccount $account): array
    {
        return $this->paginated($account, '/server_types', 'server_types');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createServer(HetznerAccount $account, array $payload): array
    {
        return $this->post($account, '/servers', $payload);
    }

    public function server(HetznerAccount $account, int|string $serverId): ?array
    {
        $response = $this->request($account)->get('/servers/'.$serverId);

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new RuntimeException('Hetzner server lookup failed: '.$response->body());
        }

        return $response->json('server');
    }

    public function powerOn(HetznerAccount $account, int|string $serverId): array
    {
        return $this->post($account, '/servers/'.$serverId.'/actions/poweron');
    }

    public function shutdown(HetznerAccount $account, int|string $serverId): array
    {
        return $this->post($account, '/servers/'.$serverId.'/actions/shutdown');
    }

    public function powerOff(HetznerAccount $account, int|string $serverId): array
    {
        return $this->post($account, '/servers/'.$serverId.'/actions/poweroff');
    }

    public function reboot(HetznerAccount $account, int|string $serverId): array
    {
        return $this->post($account, '/servers/'.$serverId.'/actions/reboot');
    }

    public function walletFreezeFirewall(HetznerAccount $account): int
    {
        $name = 'aviato-wallet-freeze';
        foreach ($this->paginated($account, '/firewalls', 'firewalls') as $firewall) {
            if (($firewall['name'] ?? null) === $name) {
                $rules = $firewall['rules'] ?? [];
                if (count($rules) !== 1 || ($rules[0]['direction'] ?? null) !== 'out'
                    || ($rules[0]['protocol'] ?? null) !== 'tcp' || (string) ($rules[0]['port'] ?? '') !== '9'
                    || ! in_array('192.0.2.1/32', $rules[0]['destination_ips'] ?? [], true)) {
                    throw new RuntimeException('The reserved wallet freeze firewall has unexpected rules.');
                }

                return (int) $firewall['id'];
            }
        }

        // Hetzner accepts outbound traffic when no outbound rule exists. A rule
        // limited to a documentation-only address makes all real outbound traffic
        // fall through to the default deny policy; no inbound rules means deny.
        $response = $this->post($account, '/firewalls', [
            'name' => $name,
            'rules' => [[
                'direction' => 'out',
                'protocol' => 'tcp',
                'port' => '9',
                'destination_ips' => ['192.0.2.1/32', '2001:db8::1/128'],
            ]],
        ]);

        $id = (int) data_get($response, 'firewall.id');
        if ($id <= 0) {
            throw new RuntimeException('Hetzner did not return the wallet freeze firewall ID.');
        }

        return $id;
    }

    public function applyFirewall(HetznerAccount $account, int $firewallId, int|string $serverId): array
    {
        return $this->post($account, '/firewalls/'.$firewallId.'/actions/apply_to_resources', [
            'apply_to' => [['type' => 'server', 'server' => ['id' => (int) $serverId]]],
        ]);
    }

    public function firewallAppliedToServer(HetznerAccount $account, int $firewallId, int|string $serverId): bool
    {
        $firewall = $this->get($account, '/firewalls/'.$firewallId)['firewall'] ?? [];

        foreach ($firewall['applied_to'] ?? [] as $resource) {
            if (($resource['type'] ?? null) === 'server'
                && (int) data_get($resource, 'server.id') === (int) $serverId) {
                return true;
            }
        }

        return false;
    }

    public function removeFirewall(HetznerAccount $account, int $firewallId, int|string $serverId): array
    {
        return $this->post($account, '/firewalls/'.$firewallId.'/actions/remove_from_resources', [
            'remove_from' => [['type' => 'server', 'server' => ['id' => (int) $serverId]]],
        ]);
    }

    public function detachFromNetwork(HetznerAccount $account, int|string $serverId, int $networkId): array
    {
        return $this->post($account, '/servers/'.$serverId.'/actions/detach_from_network', ['network' => $networkId]);
    }

    public function attachToNetwork(HetznerAccount $account, int|string $serverId, array $network): array
    {
        return $this->post($account, '/servers/'.$serverId.'/actions/attach_to_network', array_filter([
            'network' => $network['network'],
            'ip' => $network['ip'] ?? null,
            'alias_ips' => $network['alias_ips'] ?? null,
        ], static fn ($value): bool => $value !== null));
    }

    public function rebuild(HetznerAccount $account, int|string $serverId, string $image): array
    {
        return $this->post($account, '/servers/'.$serverId.'/actions/rebuild', ['image' => $image]);
    }

    public function changeType(HetznerAccount $account, int|string $serverId, string $serverType, bool $upgradeDisk = true): array
    {
        return $this->post($account, '/servers/'.$serverId.'/actions/change_type', [
            'server_type' => $serverType,
            'upgrade_disk' => $upgradeDisk,
        ]);
    }

    public function deleteServer(HetznerAccount $account, int|string $serverId): array
    {
        $response = $this->request($account)->delete('/servers/'.$serverId);

        if ($response->status() === 404) {
            return ['deleted' => false, 'missing' => true];
        }

        if ($response->failed()) {
            throw new RuntimeException('Hetzner delete failed: '.$response->body());
        }

        return $response->json() ?: ['deleted' => true];
    }

    public function waitForAction(HetznerAccount $account, int|string|null $actionId, int $timeoutSeconds = 300): array
    {
        if (! $actionId) {
            return ['status' => 'none'];
        }

        $deadline = now()->addSeconds($timeoutSeconds);
        $last = [];

        do {
            $last = $this->get($account, '/actions/'.$actionId)['action'] ?? [];

            if (($last['status'] ?? null) === 'success') {
                return $last;
            }

            if (($last['status'] ?? null) === 'error') {
                throw new RuntimeException('Hetzner action failed: '.json_encode($last['error'] ?? $last));
            }

            if (! app()->runningUnitTests()) {
                sleep(2);
            }
        } while (now()->lt($deadline));

        throw new RuntimeException('Timed out waiting for Hetzner action '.$actionId.'. Last status: '.json_encode($last));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function paginated(HetznerAccount $account, string $path, string $key, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $payload = $this->get($account, $path, $query + ['page' => $page, 'per_page' => 50]);
            $items = array_merge($items, $payload[$key] ?? []);
            $lastPage = (int) data_get($payload, 'meta.pagination.last_page', $page);
            $page++;
        } while ($page <= $lastPage);

        return $items;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(HetznerAccount $account, string $path, array $query = []): array
    {
        $response = $this->request($account)->get($path, $query);

        if ($response->failed()) {
            throw new RuntimeException('Hetzner API request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(HetznerAccount $account, string $path, array $payload = []): array
    {
        $response = $this->request($account)->post($path, $payload);

        if ($response->failed()) {
            throw new RuntimeException('Hetzner API request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    private function request(HetznerAccount $account): PendingRequest
    {
        $token = trim((string) $account->api_token);

        if ($token === '') {
            throw new RuntimeException('Hetzner API token is missing.');
        }

        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout(30)
            ->retry(2, 250);
    }
}

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

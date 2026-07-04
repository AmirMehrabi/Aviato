<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'cluster_name',
    'datacenter',
    'environment',
    'host',
    'port',
    'realm',
    'username',
    'password',
    'api_token_id',
    'api_token_secret',
    'verify_tls',
    'is_active',
    'maintenance_mode',
    'tags',
    'desired_state',
    'remote_inventory',
    'connection_status',
    'sync_status',
    'sync_error',
    'sync_pending_since',
    'synced_at',
    'last_seen_at',
    'last_status',
    'cpu_threshold_percent',
    'ram_threshold_percent',
    'disk_threshold_percent',
    'api_endpoints',
    'node_api_credentials',
])]
#[Hidden(['password', 'api_token_secret', 'node_api_credentials'])]
class ProxmoxServer extends Model
{
    public const CONNECTION_ONLINE = 'online';

    public const CONNECTION_OFFLINE = 'offline';

    public const CONNECTION_UNKNOWN = 'unknown';

    public const SYNC_SYNCED = 'synced';

    public const SYNC_PENDING = 'pending';

    public const SYNC_FAILED = 'failed';

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    public function cloudImages(): HasMany
    {
        return $this->hasMany(CloudImage::class);
    }

    public function ipPools(): HasMany
    {
        return $this->hasMany(IpPool::class);
    }

    public function cloudImageNodeMappings(): HasMany
    {
        return $this->hasMany(CloudImageNodeMapping::class);
    }

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'api_token_secret' => 'encrypted',
            'verify_tls' => 'boolean',
            'is_active' => 'boolean',
            'maintenance_mode' => 'boolean',
            'tags' => 'array',
            'desired_state' => 'array',
            'remote_inventory' => 'array',
            'last_seen_at' => 'datetime',
            'last_status' => 'array',
            'sync_pending_since' => 'datetime',
            'synced_at' => 'datetime',
            'cpu_threshold_percent' => 'integer',
            'ram_threshold_percent' => 'integer',
            'disk_threshold_percent' => 'integer',
            'api_endpoints' => 'array',
            'node_api_credentials' => 'encrypted:array',
        ];
    }

    public function baseUrl(): string
    {
        return 'https://'.rtrim($this->host, '/').':'.$this->port;
    }

    /** @return array<int, string> */
    public function apiBaseUrls(): array
    {
        return collect([$this->host, ...array_values($this->api_endpoints ?? [])])
            ->map(function (string $endpoint): string {
                $endpoint = trim($endpoint);
                if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
                    return rtrim($endpoint, '/');
                }

                return 'https://'.rtrim($endpoint, '/').':'.$this->port;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function apiBaseUrlForNode(string $node): ?string
    {
        $endpoint = $this->api_endpoints[$node] ?? null;

        if (! is_string($endpoint) || blank($endpoint)) {
            return null;
        }

        $endpoint = trim($endpoint);

        return str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')
            ? rtrim($endpoint, '/')
            : 'https://'.rtrim($endpoint, '/').':'.$this->port;
    }

    /** @return array{token_id?: string, token_secret?: string} */
    public function apiCredentialsForNode(string $node): array
    {
        $credentials = $this->node_api_credentials[$node] ?? [];

        return is_array($credentials) ? $credentials : [];
    }

    public function usesApiToken(): bool
    {
        return filled(trim((string) $this->api_token_id)) && filled(trim((string) $this->api_token_secret));
    }

    public function proxmoxUser(): string
    {
        $username = trim((string) $this->username);
        $realm = trim((string) $this->realm);

        return str_contains($username, '@') ? $username : $username.'@'.$realm;
    }

    public function finalApiTokenId(): ?string
    {
        $tokenId = trim((string) $this->api_token_id);

        if ($tokenId === '') {
            return null;
        }

        return str_contains($tokenId, '!') ? $tokenId : $this->proxmoxUser().'!'.$tokenId;
    }

    public function apiTokenAuthorizationHeader(): ?string
    {
        $finalTokenId = $this->finalApiTokenId();
        $secret = trim((string) $this->api_token_secret);

        if (! $finalTokenId || $secret === '') {
            return null;
        }

        return 'PVEAPIToken='.$finalTokenId.'='.$secret;
    }

    /** @return array<string, mixed> */
    public function desiredStateSnapshot(): array
    {
        return [
            'name' => $this->name,
            'cluster_name' => $this->cluster_name,
            'datacenter' => $this->datacenter,
            'environment' => $this->environment,
            'host' => $this->host,
            'port' => $this->port,
            'realm' => $this->realm,
            'username' => $this->username,
            'verify_tls' => $this->verify_tls,
            'is_active' => $this->is_active,
            'maintenance_mode' => $this->maintenance_mode,
            'tags' => $this->tags ?? [],
            'cpu_threshold_percent' => $this->cpu_threshold_percent,
            'ram_threshold_percent' => $this->ram_threshold_percent,
            'disk_threshold_percent' => $this->disk_threshold_percent,
            'api_endpoints' => $this->api_endpoints ?? [],
        ];
    }

    public function markPendingSync(): void
    {
        $this->forceFill([
            'desired_state' => $this->desiredStateSnapshot(),
            'sync_status' => self::SYNC_PENDING,
            'sync_error' => null,
            'sync_pending_since' => $this->sync_pending_since ?? now(),
        ]);
    }
}

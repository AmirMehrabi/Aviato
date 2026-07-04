<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProxmoxServerRequest;
use App\Http\Requests\Admin\UpdateProxmoxServerRequest;
use App\Http\Resources\Admin\ProxmoxServerResource;
use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProxmoxServerController extends Controller
{
    public function __construct(private readonly ProxmoxService $proxmox) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProxmoxServerResource::collection(
            $this->filteredQuery($request)->latest()->paginate($request->integer('per_page', 15))
        );
    }

    public function store(StoreProxmoxServerRequest $request): ProxmoxServerResource
    {
        $server = ProxmoxServer::make($this->normalizedInput($request->validated()));
        $server->markPendingSync();
        $server->save();

        return ProxmoxServerResource::make($server);
    }

    public function show(ProxmoxServer $proxmoxServer): ProxmoxServerResource|JsonResponse
    {
        try {
            $summary = $this->proxmox->summary($proxmoxServer);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to fetch live Proxmox information; returning local fallback data.',
                'error' => $exception instanceof RuntimeException ? $exception->getMessage() : 'The Proxmox request failed.',
                'fallback' => true,
                'data' => ProxmoxServerResource::make($this->markOffline($proxmoxServer, $exception)),
            ], 200);
        }

        $proxmoxServer->forceFill([
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

        $proxmoxServer = $proxmoxServer->refresh();
        $proxmoxServer->summary = $summary;

        return ProxmoxServerResource::make($proxmoxServer);
    }

    public function update(UpdateProxmoxServerRequest $request, ProxmoxServer $proxmoxServer): ProxmoxServerResource
    {
        $proxmoxServer->fill($this->normalizedInput($request->validated(), true, $proxmoxServer));
        $proxmoxServer->markPendingSync();
        $proxmoxServer->save();

        if ($request->boolean('sync_now')) {
            try {
                $this->proxmox->syncDesiredState($proxmoxServer);
            } catch (Throwable $exception) {
                $this->markOffline($proxmoxServer, $exception);
            }
        }

        return ProxmoxServerResource::make($proxmoxServer->refresh());
    }

    public function destroy(ProxmoxServer $proxmoxServer): JsonResponse
    {
        $proxmoxServer->delete();

        return response()->json(status: 204);
    }

    /** @return Builder<ProxmoxServer> */
    private function filteredQuery(Request $request): Builder
    {
        return ProxmoxServer::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('cluster_name', 'like', $search)
                        ->orWhere('host', 'like', $search)
                        ->orWhere('datacenter', 'like', $search);
                });
            })
            ->when($request->filled('datacenter'), fn ($query) => $query->where('datacenter', $request->string('datacenter')))
            ->when($request->filled('environment'), fn ($query) => $query->where('environment', $request->string('environment')))
            ->when($request->filled('connection_status'), fn ($query) => $query->where('connection_status', $request->string('connection_status')))
            ->when($request->filled('sync_status'), fn ($query) => $query->where('sync_status', $request->string('sync_status')))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')));
    }

    private function markOffline(ProxmoxServer $server, Throwable $exception): ProxmoxServer
    {
        Log::error('Marking Proxmox server offline after API failure', [
            'server_id' => $server->id,
            'server_name' => $server->name,
            'host' => $server->host,
            'port' => (int) $server->port,
            'connection_status_before' => $server->connection_status,
            'sync_status_before' => $server->sync_status,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
        ]);

        $server->forceFill([
            'connection_status' => ProxmoxServer::CONNECTION_OFFLINE,
            'sync_status' => $server->sync_status === ProxmoxServer::SYNC_SYNCED ? ProxmoxServer::SYNC_FAILED : $server->sync_status,
            'sync_error' => $exception->getMessage(),
        ])->save();

        return $server->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedInput(array $data, bool $isUpdate = false, ?ProxmoxServer $server = null): array
    {
        foreach (['host', 'realm', 'username', 'api_token_id', 'api_token_secret'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (isset($data['tags']) && is_string($data['tags'])) {
            $data['tags'] = collect(explode(',', $data['tags']))
                ->map(fn (string $tag): string => trim($tag))
                ->filter()
                ->values()
                ->all();
        }

        if (array_key_exists('node_api_endpoints', $data)) {
            $data['api_endpoints'] = collect($data['node_api_endpoints'] ?? [])
                ->map(fn (mixed $endpoint): string => trim((string) $endpoint))
                ->filter()
                ->all();
            unset($data['node_api_endpoints']);
        }

        if (array_key_exists('node_api_credentials', $data)) {
            $existing = $server?->node_api_credentials ?? [];
            $data['node_api_credentials'] = collect($data['node_api_credentials'] ?? [])
                ->map(function (array $credentials, string $node) use ($existing): array {
                    return [
                        'token_id' => trim((string) ($credentials['token_id'] ?? '')),
                        'token_secret' => filled($credentials['token_secret'] ?? null)
                            ? trim((string) $credentials['token_secret'])
                            : (string) data_get($existing, $node.'.token_secret', ''),
                    ];
                })
                ->filter(fn (array $credentials): bool => $credentials['token_id'] !== '')
                ->all();
        }

        foreach (['verify_tls', 'is_active', 'maintenance_mode'] as $booleanField) {
            if (array_key_exists($booleanField, $data)) {
                $data[$booleanField] = filter_var($data[$booleanField], FILTER_VALIDATE_BOOL);
            } elseif (! $isUpdate) {
                $data[$booleanField] = in_array($booleanField, ['verify_tls', 'is_active'], true);
            }
        }

        if (array_key_exists('password', $data) && blank($data['password'])) {
            $data['password'] = null;
        }

        if (array_key_exists('api_token_secret', $data) && blank($data['api_token_secret'])) {
            $data['api_token_secret'] = null;
        }

        return $data;
    }
}

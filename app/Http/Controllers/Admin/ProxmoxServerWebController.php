<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProxmoxServerRequest;
use App\Http\Requests\Admin\UpdateProxmoxServerRequest;
use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProxmoxServerWebController extends Controller
{
    public function __construct(private readonly ProxmoxService $proxmox) {}

    public function index(Request $request): View
    {
        $servers = ProxmoxServer::query()->latest()->get();

        return view('admin.proxmox-servers.index', [
            'servers' => $servers,
            'datacenters' => $servers->pluck('datacenter')->filter()->unique()->sort()->values(),
            'environments' => $servers->pluck('environment')->filter()->unique()->sort()->values(),
            'stats' => [
                'total' => $servers->count(),
                'online' => $servers->where('connection_status', ProxmoxServer::CONNECTION_ONLINE)->count(),
                'offline' => $servers->where('connection_status', ProxmoxServer::CONNECTION_OFFLINE)->count(),
                'pending' => $servers->where('sync_status', ProxmoxServer::SYNC_PENDING)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.proxmox-servers.create', [
            'server' => new ProxmoxServer([
                'port' => 8006,
                'realm' => 'pam',
                'environment' => 'production',
                'verify_tls' => true,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(StoreProxmoxServerRequest $request): RedirectResponse
    {
        $server = ProxmoxServer::make($this->normalizedInput($request->validated()));
        $server->markPendingSync();
        $server->save();

        if ($request->boolean('sync_now')) {
            $this->attemptSync($server);
        }

        return redirect()->route('admin.proxmox-servers.show', $server)
            ->with('status', 'Proxmox server saved. It will stay manageable even while offline.');
    }

    public function show(ProxmoxServer $proxmoxServer): View
    {
        $summary = null;
        $fallback = false;

        try {
            $summary = $this->proxmox->syncDesiredState($proxmoxServer);
        } catch (Throwable $exception) {
            $fallback = true;
            $this->markOffline($proxmoxServer, $exception);
        }

        return view('admin.proxmox-servers.show', [
            'server' => $proxmoxServer->refresh(),
            'summary' => $summary,
            'fallback' => $fallback,
        ]);
    }

    public function edit(ProxmoxServer $proxmoxServer): View
    {
        return view('admin.proxmox-servers.edit', ['server' => $proxmoxServer]);
    }

    public function update(UpdateProxmoxServerRequest $request, ProxmoxServer $proxmoxServer): RedirectResponse
    {
        $proxmoxServer->fill($this->normalizedInput($request->validated(), true));
        $proxmoxServer->markPendingSync();
        $proxmoxServer->save();

        if ($request->boolean('sync_now')) {
            $this->attemptSync($proxmoxServer);
        }

        return redirect()->route('admin.proxmox-servers.show', $proxmoxServer)
            ->with('status', 'Changes saved locally and marked for sync.');
    }

    public function destroy(ProxmoxServer $proxmoxServer): RedirectResponse
    {
        $proxmoxServer->delete();

        return redirect()->route('admin.proxmox-servers.index')
            ->with('status', 'Proxmox server removed from the control panel.');
    }

    public function sync(ProxmoxServer $proxmoxServer): RedirectResponse
    {
        Log::info('Manual Proxmox sync requested from admin UI', [
            'server_id' => $proxmoxServer->id,
            'server_name' => $proxmoxServer->name,
            'host' => $proxmoxServer->host,
            'port' => (int) $proxmoxServer->port,
            'connection_status_before' => $proxmoxServer->connection_status,
            'sync_status_before' => $proxmoxServer->sync_status,
        ]);

        $synced = $this->attemptSync($proxmoxServer);

        Log::info('Manual Proxmox sync finished from admin UI', [
            'server_id' => $proxmoxServer->id,
            'server_name' => $proxmoxServer->name,
            'synced' => $synced,
            'connection_status_after' => $proxmoxServer->refresh()->connection_status,
            'sync_status_after' => $proxmoxServer->sync_status,
            'sync_error_after' => $proxmoxServer->sync_error,
        ]);

        return back()->with($synced ? 'status' : 'error', $synced ? 'Server synced successfully.' : 'Server is offline; changes remain pending.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedInput(array $data, bool $isUpdate = false): array
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

        foreach (['verify_tls', 'is_active', 'maintenance_mode'] as $booleanField) {
            if (array_key_exists($booleanField, $data)) {
                $data[$booleanField] = filter_var($data[$booleanField], FILTER_VALIDATE_BOOL);
            } elseif (! $isUpdate) {
                $data[$booleanField] = in_array($booleanField, ['verify_tls', 'is_active'], true);
            } else {
                $data[$booleanField] = false;
            }
        }

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        if (array_key_exists('api_token_secret', $data) && blank($data['api_token_secret'])) {
            unset($data['api_token_secret']);
        }

        return $data;
    }

    private function attemptSync(ProxmoxServer $server): bool
    {
        try {
            $this->proxmox->syncDesiredState($server);

            return true;
        } catch (Throwable $exception) {
            $this->markOffline($server, $exception);

            return false;
        }
    }

    private function markOffline(ProxmoxServer $server, Throwable $exception): void
    {
        Log::error('Marking Proxmox server offline after sync failure', [
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
            'sync_error' => $exception->getMessage(),
        ])->save();
    }
}

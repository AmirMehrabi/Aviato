<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProxmoxServerRequest;
use App\Http\Requests\Admin\UpdateProxmoxServerRequest;
use App\Http\Resources\Admin\ProxmoxServerResource;
use App\Models\ProxmoxServer;
use App\Services\ProxmoxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Throwable;

class ProxmoxServerController extends Controller
{
    public function __construct(private readonly ProxmoxService $proxmox)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return ProxmoxServerResource::collection(
            ProxmoxServer::query()->latest()->paginate()
        );
    }

    public function store(StoreProxmoxServerRequest $request): ProxmoxServerResource
    {
        $server = ProxmoxServer::create($this->normalizedInput($request->validated()));

        return ProxmoxServerResource::make($server);
    }

    public function show(ProxmoxServer $proxmoxServer): ProxmoxServerResource|JsonResponse
    {
        try {
            $summary = $this->proxmox->summary($proxmoxServer);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to fetch Proxmox server information.',
                'error' => $exception instanceof RuntimeException ? $exception->getMessage() : 'The Proxmox request failed.',
                'data' => ProxmoxServerResource::make($proxmoxServer),
            ], 502);
        }

        $proxmoxServer->forceFill([
            'last_seen_at' => now(),
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
        $proxmoxServer->update($this->normalizedInput($request->validated(), true));

        return ProxmoxServerResource::make($proxmoxServer);
    }

    public function destroy(ProxmoxServer $proxmoxServer): JsonResponse
    {
        $proxmoxServer->delete();

        return response()->json(status: 204);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizedInput(array $data, bool $isUpdate = false): array
    {
        foreach (['verify_tls', 'is_active'] as $booleanField) {
            if (array_key_exists($booleanField, $data)) {
                $data[$booleanField] = filter_var($data[$booleanField], FILTER_VALIDATE_BOOL);
            } elseif (! $isUpdate) {
                $data[$booleanField] = true;
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class VirtualMachineConsoleController extends Controller
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
    ) {}

    public function show(VirtualMachine $virtualMachine): View
    {
        return view('admin.virtual-machines.console', [
            'vm' => $virtualMachine->loadMissing(['customer', 'proxmoxServer']),
            'consoleSessionUrl' => route('admin.virtual-machines.console.session', $virtualMachine, false),
        ]);
    }

    public function session(VirtualMachine $virtualMachine): JsonResponse
    {
        try {
            $this->assertConsoleReady($virtualMachine->loadMissing('proxmoxServer'));

            $console = $this->proxmox->qemuConsoleSession(
                $virtualMachine->proxmoxServer,
                (string) $virtualMachine->node,
                (int) $virtualMachine->vmid,
            );

            $ttl = max(15, (int) config('console.session_ttl', 60));

            return response()->json([
                'websocket_url' => $this->proxmoxWebsocketUrl($virtualMachine, (int) $console['port'], (string) $console['ticket']),
                'password' => (string) $console['ticket'],
                'expires_in' => $ttl,
                'expires_at' => now()->addSeconds($ttl)->toISOString(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Console session could not be started.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    private function assertConsoleReady(VirtualMachine $vm): void
    {
        if (! $vm->proxmoxServer || blank($vm->node) || blank($vm->vmid)) {
            throw new RuntimeException('This VM is not connected to Proxmox console yet.');
        }

        if ($vm->isLxc()) {
            throw new RuntimeException('Console is not available for imported LXC guests yet.');
        }

        if ($vm->isActionLocked()) {
            throw new RuntimeException('Console is unavailable while this VM is locked for deletion.');
        }

        if ($vm->provisioning_status !== VirtualMachine::PROVISION_READY) {
            throw new RuntimeException('Console is available after provisioning finishes.');
        }
    }

    private function proxmoxWebsocketUrl(VirtualMachine $vm, int $port, string $ticket): string
    {
        $path = rtrim((string) config('console.proxy_path', '/console-ws'), '/')
            .'/'.$vm->proxmox_server_id
            .'/nodes/'.rawurlencode((string) $vm->node)
            .'/qemu/'.(int) $vm->vmid
            .'/vncwebsocket';

        return $path.'?'.http_build_query([
            'port' => $port,
            'vncticket' => $ticket,
        ]);
    }
}

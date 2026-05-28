<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use App\Services\WalletService;
use App\Services\WebsockifyConsoleTokenService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ServerConsoleController extends Controller
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
        private readonly WalletService $wallets,
        private readonly WebsockifyConsoleTokenService $websockifyTokens,
    ) {}

    public function show(Request $request, VirtualMachine $virtualMachine): View
    {
        $server = $this->resolveCustomerServer($request, $virtualMachine);
        $customer = $request->user('customer');

        return view('customer.servers.console', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'server' => $server->loadMissing('proxmoxServer'),
            'consoleSessionUrl' => route('customer.servers.console.session', $server, false),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function session(Request $request, VirtualMachine $virtualMachine): JsonResponse
    {
        $server = $this->resolveCustomerServer($request, $virtualMachine);

        try {
            $this->assertConsoleReady($server);

            $console = $this->proxmox->qemuConsoleSession(
                $server->proxmoxServer,
                (string) $server->node,
                (int) $server->vmid,
            );

            $token = Str::random(48);
            $ttl = max(15, (int) config('console.session_ttl', 60));
            $expiresAt = now()->addSeconds($ttl);

            $this->websockifyTokens->publish(
                $server->proxmoxServer,
                $token,
                (int) $console['port'],
                $expiresAt,
            );

            return response()->json([
                'session_id' => $token,
                'websocket_url' => $this->websockifyUrl((int) $server->proxmox_server_id, $token),
                'password' => (string) $console['ticket'],
                'expires_in' => $ttl,
                'expires_at' => $expiresAt->toISOString(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Console session could not be started.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    private function resolveCustomerServer(Request $request, VirtualMachine $virtualMachine): VirtualMachine
    {
        $customer = $request->user('customer');

        abort_if((int) $virtualMachine->customer_id !== (int) $customer->id, 404);
        abort_if($virtualMachine->isDeleted(), 404);

        return $virtualMachine->loadMissing('proxmoxServer');
    }

    private function assertConsoleReady(VirtualMachine $server): void
    {
        if (! $server->proxmoxServer || blank($server->node) || blank($server->vmid)) {
            throw new RuntimeException('This VM is not connected to Proxmox console yet.');
        }

        if ($server->isActionLocked()) {
            throw new RuntimeException('Console is unavailable while this VM is locked for deletion.');
        }

        if ($server->provisioning_status !== VirtualMachine::PROVISION_READY) {
            throw new RuntimeException('Console is available after provisioning finishes.');
        }
    }

    private function websockifyUrl(int $proxmoxServerId, string $token): string
    {
        return rtrim((string) config('console.websockify.public_path', '/console-ws'), '/')
            .'/'.$proxmoxServerId.'?token='.rawurlencode($token);
    }
}

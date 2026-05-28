<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ServerConsoleController extends Controller
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
        private readonly WalletService $wallets,
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
            'consoleProxyUrl' => config('console.proxy_url'),
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

            $sessionId = (string) Str::uuid();
            $ttl = max(15, (int) config('console.session_ttl', 60));

            Cache::put($this->cacheKey($sessionId), [
                'session_id' => $sessionId,
                'vm_id' => $server->id,
                'customer_id' => $server->customer_id,
                'proxmox_host' => $server->proxmoxServer->host,
                'proxmox_port' => (int) $server->proxmoxServer->port,
                'verify_tls' => (bool) $server->proxmoxServer->verify_tls,
                'node' => (string) $server->node,
                'vmid' => (int) $server->vmid,
                'port' => (int) $console['port'],
                'vncticket' => (string) $console['ticket'],
                'headers' => $console['headers'],
                'expires_at' => now()->addSeconds($ttl)->toISOString(),
            ], now()->addSeconds($ttl));

            return response()->json([
                'session_id' => $sessionId,
                'proxy_url' => config('console.proxy_url'),
                'expires_in' => $ttl,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Console session could not be started.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function proxySession(Request $request, string $session): JsonResponse
    {
        abort_unless(hash_equals((string) config('console.proxy_secret'), (string) $request->header('X-Console-Proxy-Secret')), 403);

        $payload = Cache::pull($this->cacheKey($session));

        abort_unless(is_array($payload), 404);

        return response()->json($payload);
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

    private function cacheKey(string $session): string
    {
        return 'customer-console:'.$session;
    }
}

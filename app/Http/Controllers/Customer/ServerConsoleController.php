<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Services\ProjectAccessService;
use App\Services\ProxmoxService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ServerConsoleController extends Controller
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
        private readonly ProjectAccessService $projects,
        private readonly WalletService $wallets,
    ) {}

    public function show(Request $request, VirtualMachine $virtualMachine): View
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine);
        $customer = $request->user('customer');

        return view('customer.servers.console', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'activeProject' => $server->project,
            'activeMembership' => $this->projects->membership($server->project, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'server' => $server->loadMissing('proxmoxServer'),
            'consoleSessionUrl' => route('customer.servers.console.session', $server, false),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function session(Request $request, VirtualMachine $virtualMachine): JsonResponse
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine);

        try {
            $this->assertConsoleReady($server);

            $console = $this->proxmox->qemuConsoleSession(
                $server->proxmoxServer,
                (string) $server->node,
                (int) $server->vmid,
            );

            $ttl = max(15, (int) config('console.session_ttl', 60));
            $expiresAt = now()->addSeconds($ttl);

            return response()->json([
                'websocket_url' => $this->proxmoxWebsocketUrl($server, (int) $console['port'], (string) $console['ticket']),
                'password' => (string) $console['ticket'],
                'expires_in' => $ttl,
                'expires_at' => $expiresAt->toISOString(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'نشست کنسول ایجاد نشد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.',
            ], 422);
        }
    }

    public function redirectSession(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine);

        return redirect()
            ->route('customer.servers.console.show', $server)
            ->with('error', 'برای ساخت نشست Console باید از صفحه Console استفاده کنید.');
    }

    private function assertConsoleReady(VirtualMachine $server): void
    {
        if (! $server->isProxmox()) {
            throw new RuntimeException('کنسول برای این ماشین مجازی در دسترس نیست.');
        }

        if (! $server->proxmoxServer || blank($server->node) || blank($server->vmid)) {
            throw new RuntimeException('کنسول این ماشین مجازی هنوز آماده نیست.');
        }

        if ($server->isActionLocked()) {
            throw new RuntimeException('کنسول هنگام حذف ماشین مجازی در دسترس نیست.');
        }

        if ($server->provisioning_status !== VirtualMachine::PROVISION_READY) {
            throw new RuntimeException('Console is available after provisioning finishes.');
        }
    }

    private function proxmoxWebsocketUrl(VirtualMachine $server, int $port, string $ticket): string
    {
        $path = rtrim((string) config('console.proxy_path', '/console-ws'), '/')
            .'/'.$server->proxmox_server_id
            .'/nodes/'.rawurlencode((string) $server->node)
            .'/qemu/'.(int) $server->vmid
            .'/vncwebsocket';

        return $path.'?'.http_build_query([
            'port' => $port,
            'vncticket' => $ticket,
        ]);
    }
}

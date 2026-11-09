<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Services\ProjectAccessService;
use App\Services\VmConsoleService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerConsoleController extends Controller
{
    public function __construct(
        private readonly VmConsoleService $console,
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
            'powerActions' => $this->console->powerActions($server, 'customer', $this->projects->canManageVms($server->project, $customer) && ! $customer->isSuspended()),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function session(Request $request, VirtualMachine $virtualMachine): JsonResponse
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine);

        return $this->console->session($request, $server->loadMissing('proxmoxServer'), 'customer');
    }

    public function redirectSession(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine);

        return redirect()
            ->route('customer.servers.console.show', $server)
            ->with('error', 'برای ساخت نشست Console باید از صفحه Console استفاده کنید.');
    }
}

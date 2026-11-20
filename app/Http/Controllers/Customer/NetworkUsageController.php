<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Services\BillingService;
use App\Services\NetworkBillingPolicyService;
use App\Services\NetworkUsageReportService;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NetworkUsageController extends Controller
{
    public function __construct(private readonly ProjectAccessService $projects, private readonly NetworkUsageReportService $reports, private readonly WalletService $wallets) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewVms($project, $customer), 404);
        $vms = $this->projects->visibleVms($project, $customer)->with('bundle')->get();

        return view('customer.network.index', $this->layoutData($request) + [
            'vms' => $vms, 'summaries' => $vms->mapWithKeys(fn ($vm) => [$vm->id => $this->reports->vmSummary($vm)]),
            'reports' => $this->reports, 'wallets' => $this->wallets,
        ]);
    }

    public function show(Request $request, VirtualMachine $virtualMachine, BillingService $billing, NetworkBillingPolicyService $policies): View
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine);
        $server->loadMissing(['proxmoxServer', 'infrastructureLocation.hetznerAccount', 'project.owner']);
        $timezone = $policies->effective($server)['timezone'];
        $months = $this->reports->trafficMonths($server, $timezone);
        $selectedMonth = $request->query('month', array_key_first($months));
        abort_unless(is_string($selectedMonth) && isset($months[$selectedMonth]), 404);

        $month = $months[$selectedMonth];
        $from = $month->toCarbon()->startOfDay();
        $to = $month->addMonths()->toCarbon()->startOfDay();
        $customer = $request->user('customer');

        return view('customer.servers.detail', array_merge($this->layoutData($request), [
            'server' => $server,
            'tab' => 'network',
            'activeProject' => $server->project,
            'activeMembership' => $this->projects->membership($server->project, $customer),
            'canManageServer' => $this->projects->canManageVms($server->project, $customer),
            'billing' => $billing,
            'summary' => $this->reports->vmSummary($server),
            'reports' => $this->reports,
            'wallets' => $this->wallets,
            'months' => $months,
            'selectedMonth' => $selectedMonth,
            'chartStart' => $from->toDateString(),
            'chartEnd' => $to->copy()->subDay()->toDateString(),
        ], $this->reports->trafficForRange($server, $from, $to)));
    }

    private function layoutData(Request $request): array
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);

        return ['customer' => $customer, 'activeProject' => $project, 'activeMembership' => $this->projects->membership($project, $customer),
            'projects' => $this->projects->projectsFor($customer), 'wallet' => $this->wallets->walletFor($project->owner)];
    }
}

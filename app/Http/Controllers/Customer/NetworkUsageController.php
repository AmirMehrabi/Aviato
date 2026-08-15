<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\NetworkUsageBucket;
use App\Models\VirtualMachine;
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

    public function show(Request $request, VirtualMachine $virtualMachine): View
    {
        $vm = $this->projects->resolveCustomerVm($request, $virtualMachine);
        $summary = $this->reports->vmSummary($vm);
        $period = $summary['period'];

        return view('customer.network.show', $this->layoutData($request) + [
            'vm' => $vm, 'summary' => $summary, 'reports' => $this->reports, 'wallets' => $this->wallets,
            'daily' => $period ? $this->reports->daily($vm, $period->period_start, $period->period_end) : collect(),
            'dataState' => [
                'partial' => NetworkUsageBucket::query()->where('virtual_machine_id', $vm->id)->where('completeness', 'partial')->count(),
                'missing' => NetworkUsageBucket::query()->where('virtual_machine_id', $vm->id)->where('completeness', 'missing')->count(),
            ],
        ]);
    }

    private function layoutData(Request $request): array
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);

        return ['customer' => $customer, 'activeProject' => $project, 'activeMembership' => $this->projects->membership($project, $customer),
            'projects' => $this->projects->projectsFor($customer), 'wallet' => $this->wallets->walletFor($project->owner)];
    }
}

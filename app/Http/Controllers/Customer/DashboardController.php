<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmDisk;
use App\Services\BillingService;
use App\Services\ProjectAccessService;
use App\Services\UsageBillingService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly BillingService $billing,
        private readonly ProjectAccessService $projects,
        private readonly UsageBillingService $usageBilling,
    ) {}

    public function __invoke(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        $canViewVms = $this->projects->canViewVms($activeProject, $customer);
        $canViewBilling = $this->projects->canViewBilling($activeProject, $customer);
        $canManageVms = $this->projects->canManageVms($activeProject, $customer);
        abort_unless($canViewVms || $canViewBilling, 404);

        $wallet = $this->wallets->walletFor($activeProject->owner);
        $virtualMachines = $canViewVms
            ? $this->projects->visibleVms($activeProject, $customer)->with(['bundle', 'disks', 'proxmoxServer'])->latest()->get()
            : collect();
        $monthlyCostFor = function (VirtualMachine $vm): int {
            if ($vm->isActionLocked()) {
                return 0;
            }

            return ($vm->isRunning() ? $this->billing->estimateMonthly($vm) : $this->billing->estimateStoppedMonthly($vm))
                + $vm->disks->where('status', VmDisk::STATUS_READY)->sum(fn ($disk): int => (int) round($this->billing->extraDiskHourly($disk) * ResourceRate::hoursPerMonth()));
        };

        $summary = [
            'running' => $virtualMachines->where('status', VirtualMachine::STATUS_RUNNING)->count(),
            'stopped' => $virtualMachines->where('status', VirtualMachine::STATUS_STOPPED)->count(),
            'pending' => $virtualMachines->where('provisioning_status', VirtualMachine::PROVISION_PENDING)->count(),
            'failed' => $virtualMachines->where('provisioning_status', VirtualMachine::PROVISION_FAILED)->count(),
            'deleting' => $virtualMachines->where('status', VirtualMachine::STATUS_DELETING)->count(),
            'monthly_spend' => $canViewBilling ? $virtualMachines->sum($monthlyCostFor) : 0,
            'unbilled_accrued' => 0,
        ];
        $pendingUsage = ! $canViewBilling
            ? 0
            : ($canViewVms
                ? $virtualMachines
                    ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                    ->sum(fn (VirtualMachine $vm): int => $this->usageBilling->estimateVmUsage($vm)['amount'])
                : $this->usageBilling->projectPendingUsage($activeProject->id));
        $summary['unbilled_accrued'] = $pendingUsage;
        $invoiceQuery = $canViewBilling
            ? $activeProject->owner->invoices()
                ->whereHas('items', function ($query) use ($activeProject): void {
                    $query->where('meta->project_id', $activeProject->id)
                        ->orWhereNull('meta->project_id');
                })
            : null;
        $latestInvoice = $invoiceQuery ? (clone $invoiceQuery)->latest('period_start')->first() : null;

        $vmRows = $virtualMachines->map(function (VirtualMachine $vm): array {
            $status = match ($vm->status) {
                VirtualMachine::STATUS_RUNNING => 'روشن',
                VirtualMachine::STATUS_STOPPED => 'خاموش',
                VirtualMachine::STATUS_SUSPENDED => 'تعلیق',
                VirtualMachine::STATUS_DELETING => 'در حال حذف',
                default => 'نامشخص',
            };
            $statusClass = match ($vm->status) {
                VirtualMachine::STATUS_RUNNING => 'bg-emerald-50 text-emerald-700',
                VirtualMachine::STATUS_STOPPED, VirtualMachine::STATUS_SUSPENDED => 'bg-red-50 text-red-700',
                VirtualMachine::STATUS_DELETING => 'bg-amber-50 text-amber-700',
                default => 'bg-slate-100 text-slate-700',
            };
            $provisioningStatus = match ($vm->provisioning_status) {
                VirtualMachine::PROVISION_READY => 'آماده',
                VirtualMachine::PROVISION_PENDING => 'در حال آماده سازی',
                VirtualMachine::PROVISION_FAILED => 'ناموفق',
                default => $vm->provisioning_status ?: '-',
            };
            $provisioningClass = match ($vm->provisioning_status) {
                VirtualMachine::PROVISION_READY => 'bg-emerald-50 text-emerald-700',
                VirtualMachine::PROVISION_PENDING => 'bg-blue-50 text-[#0069FF]',
                VirtualMachine::PROVISION_FAILED => 'bg-red-50 text-red-600',
                default => 'bg-slate-100 text-slate-600',
            };
            $needsAttention = $vm->provisioning_status === VirtualMachine::PROVISION_FAILED
                || $vm->deleteAttemptIsStale()
                || ($vm->isDeleting() && $vm->delete_failed_at !== null);
            $consoleReady = $vm->isRunning()
                && $vm->isProxmox()
                && ! $vm->isLxc()
                && $vm->proxmoxServer
                && filled($vm->node)
                && filled($vm->vmid)
                && $vm->provisioning_status === VirtualMachine::PROVISION_READY
                && ! $vm->isActionLocked();

            return [
                'name' => $vm->display_name,
                'ip' => $vm->ip_address ?: 'بدون IP',
                'hostname' => $vm->hostname ?: '-',
                'status' => $status,
                'statusClass' => $statusClass,
                'provisioningStatus' => $provisioningStatus,
                'provisioningClass' => $provisioningClass,
                'dot' => match ($vm->status) {
                    VirtualMachine::STATUS_RUNNING => 'bg-emerald-500',
                    VirtualMachine::STATUS_STOPPED, VirtualMachine::STATUS_SUSPENDED => 'bg-red-500',
                    VirtualMachine::STATUS_DELETING => 'bg-amber-500',
                    default => $needsAttention ? 'bg-red-500' : 'bg-slate-400',
                },
                'cpu' => $vm->cpu_cores.' هسته',
                'ram' => $vm->ram_gb.' گیگ',
                'needsAttention' => $needsAttention,
                'consoleReady' => $consoleReady,
                'url' => route('customer.servers.show', $vm, false),
                'consoleUrl' => route('customer.servers.console.show', $vm, false),
            ];
        })->sortByDesc(fn (array $vm): int => $vm['needsAttention'] ? 2 : ($vm['provisioningStatus'] === 'در حال آماده سازی' ? 1 : 0))->values();

        $dashboardStats = [
            'total' => $virtualMachines->count(),
            'monthly_spend' => $summary['monthly_spend'],
        ];
        $projects = $this->projects->projectsFor($customer);
        $newWorkspaceNotification = $customer->unreadNotifications()
            ->get()
            ->first(fn ($notification): bool => data_get($notification->data, 'event') === 'workspace_added');
        $newWorkspaceProject = $newWorkspaceNotification
            ? $projects->firstWhere('id', (int) data_get($newWorkspaceNotification->data, 'project_id'))
            : null;

        return view('customer.dashboard', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $projects,
            'newWorkspaceProject' => $newWorkspaceProject,
            'newWorkspaceNotification' => $newWorkspaceNotification,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'virtualMachines' => $virtualMachines,
            'summary' => $summary,
            'pendingUsage' => $pendingUsage,
            'vmRows' => $vmRows,
            'dashboardStats' => $dashboardStats,
            'latestInvoice' => $latestInvoice,
            'canViewVms' => $canViewVms,
            'canViewBilling' => $canViewBilling,
            'canManageVms' => $canManageVms,
        ]);
    }
}

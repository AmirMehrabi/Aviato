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
            ? $this->projects->visibleVms($activeProject, $customer)->with(['bundle', 'disks'])->latest()->get()
            : collect();
        $transactions = $wallet->transactions()
            ->where(function ($query) use ($activeProject): void {
                $query->where('metadata->project_id', $activeProject->id)
                    ->orWhereNull('metadata->project_id');
            })
            ->with('createdBy')
            ->latest()
            ->limit(5)
            ->get();
        $summary = [
            'running' => $virtualMachines->where('status', VirtualMachine::STATUS_RUNNING)->count(),
            'stopped' => $virtualMachines->where('status', VirtualMachine::STATUS_STOPPED)->count(),
            'monthly_spend' => $virtualMachines
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => ($vm->isRunning() ? $this->billing->estimateMonthly($vm) : $this->billing->estimateStoppedMonthly($vm))
                    + $vm->disks->where('status', VmDisk::STATUS_READY)->sum(fn ($disk): int => (int) round($this->billing->extraDiskHourly($disk) * ResourceRate::hoursPerMonth()))),
            'unbilled_accrued' => 0,
        ];
        $pendingUsage = $canViewVms
            ? $virtualMachines
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => $this->usageBilling->estimateVmUsage($vm)['amount'])
            : $this->usageBilling->projectPendingUsage($activeProject->id);
        $summary['unbilled_accrued'] = $pendingUsage;
        $latestInvoice = $activeProject->owner->invoices()->latest('period_start')->first();

        $vmRows = $virtualMachines->map(function (VirtualMachine $vm): array {
            $monthlyCost = $vm->isRunning()
                ? $this->billing->estimateMonthly($vm)
                : $this->billing->estimateStoppedMonthly($vm);

            return [
                'id' => $vm->uuid,
                'name' => $vm->name,
                'ip' => $vm->ip_address ?: 'بدون IP',
                'region' => $vm->node ?: 'نامشخص',
                'plan' => $vm->bundle?->name ?: sprintf('%d vCPU / %dGB', $vm->cpu_cores, $vm->ram_gb),
                'status' => $vm->status === VirtualMachine::STATUS_RUNNING ? 'روشن' : 'متوقف',
                'statusClass' => $vm->status === VirtualMachine::STATUS_RUNNING ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700',
                'dot' => $vm->status === VirtualMachine::STATUS_RUNNING ? 'bg-emerald-500' : 'bg-slate-400',
                'cpu' => $vm->cpu_cores.' Core',
                'ram' => $vm->ram_gb.' GB',
                'disk' => $vm->disk_gb.' GB',
                'cost' => $monthlyCost,
                'url' => route('customer.servers.show', $vm, false),
            ];
        });

        $dashboardStats = [
            'total' => $virtualMachines->count(),
            'cpu' => $virtualMachines->sum('cpu_cores'),
            'ram' => $virtualMachines->sum('ram_gb'),
            'disk' => $virtualMachines->sum('disk_gb'),
            'monthly_spend' => $vmRows->sum('cost'),
        ];

        $notifications = [
            ['title' => 'وضعیت کیف پول', 'body' => $wallet->balance < 0 ? 'کیف پول وارد محدوده بدهی شده است و بهتر است آن را شارژ کنید.' : 'کیف پول فعال است و تراکنش ها در لحظه ثبت می شوند.', 'tone' => $wallet->balance < 0 ? 'bg-red-500' : 'bg-emerald-500'],
            ['title' => 'آخرین صورتحساب', 'body' => $latestInvoice ? 'آخرین صورتحساب شما با شماره '.$latestInvoice->number.' آماده مشاهده است.' : 'هنوز صورتحساب ماهانه ای برای حساب شما صادر نشده است.', 'tone' => 'bg-amber-500'],
        ];

        return view('customer.dashboard', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'transactions' => $transactions,
            'wallets' => $this->wallets,
            'virtualMachines' => $virtualMachines,
            'summary' => $summary,
            'pendingUsage' => $pendingUsage,
            'vmRows' => $vmRows,
            'dashboardStats' => $dashboardStats,
            'notifications' => $notifications,
            'latestInvoice' => $latestInvoice,
            'invoiceCount' => $activeProject->owner->invoices()->count(),
            'canViewVms' => $canViewVms,
            'canViewBilling' => $canViewBilling,
            'canManageVms' => $canManageVms,
        ]);
    }
}

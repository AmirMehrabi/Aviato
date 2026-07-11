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
            ? $this->projects->visibleVms($activeProject, $customer)->with(['bundle', 'disks', 'infrastructureLocation', 'cloudImage'])->latest()->get()
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
            'monthly_spend' => $virtualMachines->sum($monthlyCostFor),
            'unbilled_accrued' => 0,
        ];
        $pendingUsage = $canViewVms
            ? $virtualMachines
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => $this->usageBilling->estimateVmUsage($vm)['amount'])
            : $this->usageBilling->projectPendingUsage($activeProject->id);
        $summary['unbilled_accrued'] = $pendingUsage;
        $latestInvoice = $activeProject->owner->invoices()
            ->whereHas('items', function ($query) use ($activeProject): void {
                $query->where('meta->project_id', $activeProject->id)
                    ->orWhereNull('meta->project_id');
            })
            ->latest('period_start')
            ->first();

        $vmRows = $virtualMachines->map(function (VirtualMachine $vm) use ($monthlyCostFor): array {
            $status = match ($vm->status) {
                VirtualMachine::STATUS_RUNNING => 'روشن',
                VirtualMachine::STATUS_STOPPED => 'خاموش',
                VirtualMachine::STATUS_SUSPENDED => 'تعلیق',
                VirtualMachine::STATUS_DELETING => 'در حال حذف',
                default => 'نامشخص',
            };
            $statusClass = match ($vm->status) {
                VirtualMachine::STATUS_RUNNING => 'bg-emerald-50 text-emerald-700',
                VirtualMachine::STATUS_SUSPENDED => 'bg-red-50 text-red-600',
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
            $monthlyCost = $monthlyCostFor($vm);
            $extraDiskMonthlyCost = $vm->disks->where('status', VmDisk::STATUS_READY)
                ->sum(fn ($disk): int => (int) round($this->billing->extraDiskHourly($disk) * ResourceRate::hoursPerMonth()));
            $needsAttention = $vm->provisioning_status === VirtualMachine::PROVISION_FAILED
                || $vm->deleteAttemptIsStale()
                || ($vm->isDeleting() && $vm->delete_failed_at !== null);

            return [
                'id' => $vm->uuid,
                'name' => $vm->display_name,
                'ip' => $vm->ip_address ?: 'بدون IP',
                'hostname' => $vm->hostname ?: '-',
                'region' => $vm->infrastructureLocation?->name ?: ($vm->node ?: 'نامشخص'),
                'image' => $vm->cloudImage?->name ?: 'سیستم عامل نامشخص',
                'plan' => $vm->bundle?->name ?: sprintf('%d vCPU / %dGB', $vm->cpu_cores, $vm->ram_gb),
                'status' => $status,
                'statusClass' => $statusClass,
                'provisioningStatus' => $provisioningStatus,
                'provisioningClass' => $provisioningClass,
                'dot' => $vm->status === VirtualMachine::STATUS_RUNNING ? 'bg-emerald-500' : ($needsAttention ? 'bg-red-500' : 'bg-slate-400'),
                'cpu' => $vm->cpu_cores.' هسته',
                'ram' => $vm->ram_gb.' گیگ',
                'disk' => $vm->disk_gb.' گیگ',
                'extraDiskCount' => $vm->disks->where('status', VmDisk::STATUS_READY)->count(),
                'extraDiskMonthlyCost' => $extraDiskMonthlyCost,
                'cost' => $monthlyCost,
                'billingHint' => $vm->isRunning() ? 'CPU و RAM فعال' : 'دیسک و IP پایدار',
                'needsAttention' => $needsAttention,
                'isLocked' => $vm->isActionLocked(),
                'url' => route('customer.servers.show', $vm, false),
            ];
        })->sortByDesc(fn (array $vm): int => $vm['needsAttention'] ? 2 : ($vm['provisioningStatus'] === 'در حال آماده سازی' ? 1 : 0))->values();

        $dashboardStats = [
            'total' => $virtualMachines->count(),
            'cpu' => $virtualMachines->sum('cpu_cores'),
            'ram' => $virtualMachines->sum('ram_gb'),
            'disk' => $virtualMachines->sum('disk_gb'),
            'monthly_spend' => $vmRows->sum('cost'),
        ];

        $notifications = [
            ['title' => 'وضعیت کیف پول', 'body' => $wallet->balance < 0 ? 'کیف پول وارد محدوده بدهی شده است و بهتر است آن را شارژ کنید.' : 'کیف پول فعال است و تراکنش ها در لحظه ثبت می شوند.', 'tone' => $wallet->balance < 0 ? 'bg-red-500' : 'bg-emerald-500', 'url' => route('customer.wallet.show', ['topup' => 1], false), 'action' => $wallet->balance < 0 ? 'افزایش اعتبار' : 'مشاهده کیف پول'],
            ['title' => 'آخرین صورتحساب', 'body' => $latestInvoice ? 'آخرین صورتحساب شما با شماره '.$latestInvoice->number.' آماده مشاهده است.' : 'هنوز صورتحساب ماهانه ای برای حساب شما صادر نشده است.', 'tone' => 'bg-amber-500', 'url' => $latestInvoice ? route('customer.invoices.show', $latestInvoice, false) : route('customer.invoices.index', [], false), 'action' => 'مشاهده صورتحساب'],
        ];

        if ($summary['failed'] > 0) {
            $failedVm = $vmRows->first(fn (array $vm): bool => $vm['provisioningStatus'] === 'ناموفق');
            $notifications[] = ['title' => 'نیازمند بررسی', 'body' => $summary['failed'].' ماشین آماده سازی ناموفق دارد.', 'tone' => 'bg-red-500', 'url' => $failedVm['url'] ?? route('customer.servers.index', [], false), 'action' => 'بررسی ماشین'];
        }

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
            'invoiceCount' => $activeProject->owner->invoices()
                ->whereHas('items', function ($query) use ($activeProject): void {
                    $query->where('meta->project_id', $activeProject->id)
                        ->orWhereNull('meta->project_id');
                })
                ->count(),
            'canViewVms' => $canViewVms,
            'canViewBilling' => $canViewBilling,
            'canManageVms' => $canManageVms,
        ]);
    }
}

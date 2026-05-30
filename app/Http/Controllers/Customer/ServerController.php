<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteVirtualMachineJob;
use App\Models\CloudImage;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmBundle;
use App\Services\BillingService;
use App\Services\CloudVmProvisioningService;
use App\Services\UsageBillingService;
use App\Services\VmUpgradeService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ServerController extends Controller
{
    private const MINIMUM_CREATE_BALANCE = 1000000;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly BillingService $billing,
        private readonly UsageBillingService $usageBilling,
        private readonly CloudVmProvisioningService $cloudProvisioning,
        private readonly VmUpgradeService $upgrades,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                VirtualMachine::STATUS_RUNNING,
                VirtualMachine::STATUS_STOPPED,
                VirtualMachine::STATUS_SUSPENDED,
                VirtualMachine::STATUS_DELETING,
            ])],
        ]);

        $servers = $customer->virtualMachines()
            ->notDeleted()
            ->with(['bundle', 'proxmoxServer', 'cloudImage'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('hostname', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('node', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $summarySource = $customer->virtualMachines()->notDeleted()->with(['bundle', 'disks'])->get();
        $pendingUsage = $summarySource
            ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
            ->sum(fn (VirtualMachine $vm): int => $this->usageBilling->estimateVmUsage($vm)['amount']);
        $monthlySpend = $summarySource
            ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
            ->sum(fn (VirtualMachine $vm): int => ($vm->isRunning()
                ? $this->billing->estimateMonthly($vm)
                : $this->billing->estimateStoppedMonthly($vm))
                + $vm->disks->where('status', 'ready')->sum(fn ($disk): int => (int) round($this->billing->extraDiskHourly($disk) * ResourceRate::hoursPerMonth())));

        return view('customer.servers.index', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'servers' => $servers,
            'serverRows' => $servers->getCollection()->map(fn (VirtualMachine $server): array => [
                'id' => $server->uuid,
                'name' => $server->name,
                'hostname' => $server->hostname ?: '-',
                'ip' => $server->ip_address ?: 'بدون IP',
                'node' => $server->node ?: 'نامشخص',
                'location' => $server->proxmoxServer?->name ?: 'local',
                'plan' => $server->bundle?->name ?: 'Custom',
                'image' => $server->cloudImage?->name ?: 'Image نامشخص',
                'resources' => sprintf('%d CPU / %dGB RAM / %dGB Disk', $server->cpu_cores, $server->ram_gb, $server->disk_gb),
                'monthly_cost' => $server->isActionLocked()
                    ? 0
                    : ($server->isRunning()
                        ? $this->billing->estimateMonthly($server)
                        : $this->billing->estimateStoppedMonthly($server)),
                'billing_hint' => $server->isRunning() ? 'CPU/RAM فعال' : 'دیسک و IP پایدار',
                'status' => $server->status,
                'status_label' => $this->statusLabel($server->status),
                'status_class' => $this->statusClass($server->status),
                'provisioning_status' => $server->provisioning_status,
                'provisioning_label' => $this->provisioningLabel($server->provisioning_status),
                'provisioning_class' => $this->provisioningClass($server->provisioning_status),
                'provisioning_pending' => $server->provisioning_status === VirtualMachine::PROVISION_PENDING,
                'is_deleting' => $server->isDeleting(),
                'is_deleted' => $server->isDeleted(),
                'is_locked' => $server->isActionLocked(),
                'ssh_ready' => $server->ip_address && $server->provisioning_status === VirtualMachine::PROVISION_READY,
                'console_ready' => $server->proxmoxServer && $server->node && $server->vmid && $server->provisioning_status === VirtualMachine::PROVISION_READY && ! $server->isActionLocked(),
                'show_url' => route('customer.servers.show', $server, false),
                'console_url' => route('customer.servers.console.show', $server, false),
                'monitoring_url' => route('customer.monitoring.index', ['server' => $server->uuid], false),
                'backup_url' => route('customer.backups.index', [], false),
            ])->values(),
            'filters' => $filters,
            'billing' => $this->billing,
            'summary' => [
                'total' => $summarySource->count(),
                'running' => $summarySource->where('status', VirtualMachine::STATUS_RUNNING)->count(),
                'stopped' => $summarySource->where('status', VirtualMachine::STATUS_STOPPED)->count(),
                'pending' => $summarySource->where('provisioning_status', VirtualMachine::PROVISION_PENDING)->count(),
                'failed' => $summarySource->where('provisioning_status', VirtualMachine::PROVISION_FAILED)->count(),
                'deleting' => $summarySource->where('status', VirtualMachine::STATUS_DELETING)->count(),
                'pending_usage' => $pendingUsage,
                'monthly_spend' => $monthlySpend,
            ],
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $ids = collect((array) $request->query('ids', []))
            ->map(fn ($id): string => (string) $id)
            ->filter(fn (string $id): bool => $id !== '')
            ->filter()
            ->unique()
            ->values();

        $servers = $customer->virtualMachines()
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('uuid', $ids))
            ->get(['id', 'uuid', 'status', 'provisioning_status', 'deleted_at']);

        return response()->json([
            'servers' => $servers->map(fn (VirtualMachine $server): array => [
                'id' => $server->uuid,
                'status' => $server->status,
                'status_label' => $this->statusLabel($server->status),
                'status_class' => $this->statusClass($server->status),
                'provisioning_status' => $server->provisioning_status,
                'provisioning_label' => $this->provisioningLabel($server->provisioning_status),
                'provisioning_class' => $this->provisioningClass($server->provisioning_status),
                'provisioning_pending' => $server->provisioning_status === VirtualMachine::PROVISION_PENDING,
                'action_pending' => $server->provisioning_status === VirtualMachine::PROVISION_PENDING || $server->isDeleting(),
                'is_deleting' => $server->isDeleting(),
                'is_deleted' => $server->isDeleted(),
            ])->values(),
        ]);
    }

    public function create(Request $request): View
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);
        $cloudImages = CloudImage::query()
            ->with('proxmoxServer')
            ->where('is_active', true)
            ->orderBy('os_family')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('customer.servers.create', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'minimumCreateBalance' => self::MINIMUM_CREATE_BALANCE,
            'canCreateVps' => $wallet->balance >= self::MINIMUM_CREATE_BALANCE,
            'bundles' => VmBundle::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('monthly_price')
                ->get(),
            'cloudImages' => $cloudImages,
            'osFamilies' => $this->osFamilies($cloudImages),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);

        if ($wallet->balance < self::MINIMUM_CREATE_BALANCE) {
            return back()
                ->withInput($request->except('login_password'))
                ->with('error', 'برای ساخت VPS حداقل موجودی کیف پول باید ۱,۰۰۰,۰۰۰ ریال باشد.');
        }

        $data = $request->validate([
            'cloud_image_id' => ['required', 'integer', 'exists:cloud_images,id,is_active,1'],
            'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'],
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'login_username' => ['nullable', 'string', 'max:64'],
            'login_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'ssh_public_key' => ['nullable', 'string', 'max:5000'],
            'cpu_cores' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'disk_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
        ]);

        $data['start_after_create'] = true;
        $data['onboot'] = false;

        try {
            $result = $this->cloudProvisioning->create($customer, $data);

            return redirect()
                ->route('customer.servers.index')
                ->with('status', 'درخواست ساخت VPS ثبت شد. IP: '.$result['vm']->ip_address)
                ->with('provisioning_password', $result['password']);
        } catch (Throwable $exception) {
            return back()
                ->withInput($request->except('login_password'))
                ->with('error', 'ساخت VPS ممکن نیست: '.$exception->getMessage());
        }
    }

    public function show(Request $request, VirtualMachine $virtualMachine): View
    {
        $server = $this->resolveCustomerServer($request, $virtualMachine);
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);

        $server->loadMissing([
            'bundle',
            'proxmoxServer',
            'cloudImage',
            'reservedIpAddress.pool',
            'backupPolicy',
            'backups' => fn ($query) => $query->latest()->limit(5),
            'disks' => fn ($query) => $query->latest(),
            'upgradeOrders' => fn ($query) => $query->with(['toBundle', 'disk'])->latest()->limit(6),
        ]);

        $eligibleBundles = VmBundle::query()
            ->where('is_active', true)
            ->where('id', '!=', $server->vm_bundle_id)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get()
            ->filter(fn (VmBundle $bundle): bool => $bundle->cpu_cores >= $server->cpu_cores
                && $bundle->ram_gb >= $server->ram_gb
                && $bundle->disk_gb >= $server->disk_gb)
            ->values();
        $hasPendingUpgrade = $server->upgradeOrders->contains(fn ($order): bool => $order->isPending());
        $extraDiskOptions = collect([10, 25, 50, 100, 250, 500])
            ->map(fn (int $size): array => $this->upgrades->previewExtraDisk($server, $size))
            ->values();

        $monthlyCost = $server->isActionLocked()
            ? 0
            : ($server->isRunning()
                ? $this->billing->estimateMonthly($server)
                : $this->billing->estimateStoppedMonthly($server));
        $sshCommand = $server->ip_address
            ? 'ssh '.($server->login_username ?: 'root').'@'.$server->ip_address
            : null;
        $latestBackup = $server->backups->first();

        return view('customer.servers.show', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'server' => $server,
            'billing' => $this->billing,
            'monthlyCost' => $monthlyCost,
            'sshCommand' => $sshCommand,
            'statusLabel' => $this->statusLabel($server->status),
            'statusClass' => $this->statusClass($server->status),
            'provisioningLabel' => $this->provisioningLabel($server->provisioning_status),
            'provisioningClass' => $this->provisioningClass($server->provisioning_status),
            'backupSummary' => [
                'enabled' => (bool) $server->backupPolicy?->is_enabled,
                'frequency' => $server->backupPolicy?->frequency,
                'retention' => $server->backupPolicy?->retention_count,
                'next_run_at' => $server->backupPolicy?->next_run_at,
                'ready_count' => $server->backups->where('status', VmBackup::STATUS_READY)->count(),
                'latest_status' => $latestBackup?->status,
                'latest_at' => $latestBackup?->created_at,
                'latest_error' => $latestBackup?->status === VmBackup::STATUS_FAILED ? $latestBackup->error : null,
            ],
            'eligibleBundles' => $eligibleBundles,
            'bundlePreviews' => $eligibleBundles->mapWithKeys(fn (VmBundle $bundle): array => [
                $bundle->id => $this->upgrades->previewBundleUpgrade($server, $bundle),
            ]),
            'extraDiskOptions' => $extraDiskOptions,
            'hasPendingUpgrade' => $hasPendingUpgrade,
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function destroy(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $server = $this->resolveCustomerServer($request, $virtualMachine);
        $server->loadMissing(['reservedIpAddress', 'proxmoxServer']);

        if ($server->isActionLocked()) {
            return redirect()
                ->route('customer.servers.index')
                ->with('status', 'این سرور قبلا وارد صف حذف شده است.');
        }

        if (! $server->proxmoxServer || ! $server->node || ! $server->vmid) {
            return back()->with('error', 'اتصال این سرور به کامل نیست؛ حذف انجام نشد.');
        }

        $queued = false;

        try {
            DB::transaction(function () use ($server, &$queued): void {
                $locked = VirtualMachine::query()
                    ->whereKey($server->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->isActionLocked()) {
                    return;
                }

                $this->usageBilling->chargeVm($locked);

                $locked->forceFill([
                    'status' => VirtualMachine::STATUS_DELETING,
                    'delete_requested_at' => now(),
                    'delete_started_at' => null,
                    'delete_failed_at' => null,
                    'delete_error' => null,
                    'delete_task_id' => null,
                    'desired_state' => array_merge($locked->desired_state ?? [], ['status' => VirtualMachine::STATUS_DELETING]),
                ])->save();

                $queued = true;
            });

            if ($queued) {
                DeleteVirtualMachineJob::dispatch($server->id);
            }
        } catch (Throwable $exception) {
            return back()->with('error', 'درخواست حذف سرور ثبت نشد: '.$exception->getMessage());
        }

        return redirect()
            ->route('customer.servers.index')
            ->with('status', 'درخواست حذف سرور ثبت شد. تا پایان حذف، عملیات روی این سرور غیرفعال است.');
    }

    private function resolveCustomerServer(Request $request, VirtualMachine $virtualMachine): VirtualMachine
    {
        $customer = $request->user('customer');

        abort_if((int) $virtualMachine->customer_id !== (int) $customer->id, 404);
        abort_if($virtualMachine->isDeleted(), 404);

        return $virtualMachine;
    }

    private function osFamilies($cloudImages): array
    {
        $labels = [
            'ubuntu' => 'Ubuntu',
            'debian' => 'Debian',
            'rocky' => 'Rocky Linux',
            'windows' => 'Windows Server',
        ];

        return $cloudImages
            ->groupBy('os_family')
            ->map(fn ($images, string $family): array => [
                'key' => $family,
                'label' => $labels[$family] ?? str($family)->headline()->toString(),
                'logo_key' => $images->first()->logo_key ?: $family,
                'count' => $images->count(),
            ])
            ->values()
            ->all();
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            VirtualMachine::STATUS_RUNNING => 'روشن',
            VirtualMachine::STATUS_STOPPED => 'خاموش',
            VirtualMachine::STATUS_SUSPENDED => 'تعلیق',
            VirtualMachine::STATUS_DELETING => 'در حال حذف',
            VirtualMachine::STATUS_DELETED => 'حذف شده',
            default => $status ?: '-',
        };
    }

    private function statusClass(?string $status): string
    {
        return match ($status) {
            VirtualMachine::STATUS_RUNNING => 'bg-emerald-50 text-emerald-700',
            VirtualMachine::STATUS_SUSPENDED => 'bg-red-50 text-red-600',
            VirtualMachine::STATUS_DELETING => 'bg-amber-50 text-amber-700',
            VirtualMachine::STATUS_DELETED => 'bg-slate-100 text-slate-500',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    private function provisioningClass(?string $status): string
    {
        return match ($status) {
            VirtualMachine::PROVISION_READY => 'bg-emerald-50 text-emerald-700',
            VirtualMachine::PROVISION_FAILED => 'bg-red-50 text-red-600',
            VirtualMachine::PROVISION_PENDING => 'bg-blue-50 text-[#0069FF]',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    private function provisioningLabel(?string $status): string
    {
        return match ($status) {
            VirtualMachine::PROVISION_READY => 'آماده',
            VirtualMachine::PROVISION_FAILED => 'ناموفق',
            VirtualMachine::PROVISION_PENDING => 'در حال آماده سازی',
            default => $status ?: '-',
        };
    }
}

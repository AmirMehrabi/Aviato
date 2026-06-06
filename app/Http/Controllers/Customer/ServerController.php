<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Jobs\RebuildCloudVirtualMachine;
use App\Models\AppSetting;
use App\Models\CloudImage;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmBundle;
use App\Services\BillingService;
use App\Services\CloudVmProvisioningService;
use App\Services\CustomerVmQuotaService;
use App\Services\ProjectAccessService;
use App\Services\UsageBillingService;
use App\Services\VirtualMachineDeletionService;
use App\Services\VmUpgradeService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ServerController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly BillingService $billing,
        private readonly ProjectAccessService $projects,
        private readonly UsageBillingService $usageBilling,
        private readonly CloudVmProvisioningService $cloudProvisioning,
        private readonly VirtualMachineDeletionService $deletions,
        private readonly VmUpgradeService $upgrades,
        private readonly CustomerVmQuotaService $quota,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewVms($activeProject, $customer), 404);
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

        $servers = $activeProject->virtualMachines()
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

        $summarySource = $activeProject->virtualMachines()->notDeleted()->with(['bundle', 'disks'])->get();
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
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
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
                'provisioning_label' => $this->provisioningLabelForVm($server),
                'provisioning_class' => $this->provisioningClass($server->provisioning_status),
                'provisioning_pending' => $server->provisioning_status === VirtualMachine::PROVISION_PENDING,
                'is_deleting' => $server->isDeleting(),
                'delete_failed' => $server->isDeleting() && $server->delete_failed_at !== null,
                'delete_stale' => $server->deleteAttemptIsStale(),
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
                'deleting' => $summarySource
                    ->where('status', VirtualMachine::STATUS_DELETING)
                    ->whereNull('delete_failed_at')
                    ->count(),
                'delete_failed' => $summarySource
                    ->where('status', VirtualMachine::STATUS_DELETING)
                    ->whereNotNull('delete_failed_at')
                    ->count(),
                'delete_stale' => $summarySource->filter->deleteAttemptIsStale()->count(),
                'pending_usage' => $pendingUsage,
                'monthly_spend' => $monthlySpend,
            ],
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewVms($activeProject, $customer), 404);
        $ids = collect((array) $request->query('ids', []))
            ->map(fn ($id): string => (string) $id)
            ->filter(fn (string $id): bool => $id !== '')
            ->filter()
            ->unique()
            ->values();

        $servers = $activeProject->virtualMachines()
            ->with(['proxmoxServer', 'cloudImage'])
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('uuid', $ids))
            ->get([
                'id',
                'uuid',
                'proxmox_server_id',
                'cloud_image_id',
                'vmid',
                'name',
                'hostname',
                'node',
                'ip_address',
                'login_username',
                'login_password',
                'status',
                'provisioning_status',
                'remote_state',
                'delete_requested_at',
                'delete_started_at',
                'delete_failed_at',
                'deleted_at',
                'updated_at',
            ]);

        return response()->json([
            'servers' => $servers->map(fn (VirtualMachine $server): array => [
                'id' => $server->uuid,
                'status' => $server->status,
                'status_label' => $this->statusLabel($server->status),
                'status_class' => $this->statusClass($server->status),
                'provisioning_status' => $server->provisioning_status,
                'provisioning_label' => $this->provisioningLabelForVm($server),
                'provisioning_class' => $this->provisioningClass($server->provisioning_status),
                'provisioning_pending' => $server->provisioning_status === VirtualMachine::PROVISION_PENDING,
                'action_pending' => $server->provisioning_status === VirtualMachine::PROVISION_PENDING || ($server->isDeleting() && $server->delete_failed_at === null && ! $server->deleteAttemptIsStale()),
                'is_deleting' => $server->isDeleting(),
                'delete_failed' => $server->isDeleting() && $server->delete_failed_at !== null,
                'delete_stale' => $server->deleteAttemptIsStale(),
                'is_deleted' => $server->isDeleted(),
                'is_rebuilding' => $this->isRebuilding($server),
                'rebuild_error' => data_get($server->remote_state, 'rebuild_error'),
                'ip' => $server->ip_address ?: 'بدون IP',
                'hostname' => $server->hostname ?: 'hostname-not-set',
                'node' => $server->node ?: 'node-not-set',
                'vmid' => $server->vmid,
                'login_username' => $server->login_username ?: '-',
                'has_password' => filled($server->login_password),
                'console_ready' => $server->proxmoxServer && $server->node && $server->vmid && $server->provisioning_status === VirtualMachine::PROVISION_READY && ! $server->isActionLocked(),
                'ssh_command' => $server->ip_address ? 'ssh '.($server->login_username ?: 'root').'@'.$server->ip_address : null,
                'updated_at' => $server->updated_at?->toISOString(),
            ])->values(),
        ]);
    }

    public function create(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canManageVms($activeProject, $customer), 404);
        $quota = $this->quota->snapshot($activeProject->owner);
        $wallet = $this->wallets->walletFor($activeProject->owner);
        $cloudImages = CloudImage::query()
            ->with(['proxmoxServer', 'allowedBundles'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('customer.servers.create', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'quota' => $quota,
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
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canManageVms($activeProject, $customer), 404);
        $quota = $this->quota->snapshot($activeProject->owner);
        $wallet = $this->wallets->walletFor($activeProject->owner);

        $data = $request->validate([
            'cloud_image_id' => ['required', 'integer', 'exists:cloud_images,id,is_active,1'],
            'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'],
            'login_username' => ['nullable', 'string', 'max:64'],
            'login_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'ssh_public_key' => ['nullable', 'string', 'max:5000', $this->sshPublicKeyRule()],
            'cpu_cores' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'disk_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
        ]);

        $data['start_after_create'] = true;
        $data['onboot'] = false;
        $data['network_bridge'] = 'vmbr1';

        $image = CloudImage::query()
            ->where('is_active', true)
            ->with('allowedBundles')
            ->findOrFail($data['cloud_image_id']);

        if ($image->cloud_init_enabled
            && filled((string) ($data['login_password'] ?? ''))
            && (string) $data['login_password'] !== (string) $request->input('login_password_confirmation')) {
            return back()
                ->withErrors(['login_password' => 'تکرار رمز عبور با رمز عبور یکسان نیست.'])
                ->withInput($this->safeCreateInput($request));
        }

        if (! empty($data['vm_bundle_id']) && ! $image->allowedBundles->contains(fn ($bundle): bool => (int) $bundle->id === (int) $data['vm_bundle_id'])) {
            return back()
                ->withErrors([
                    'vm_bundle_id' => 'این پلن برای این Cloud Image مجاز نیست.',
                ])
                ->withInput($this->safeCreateInput($request));
        }

        $bundle = $image->allowedBundles->first(fn (VmBundle $bundle): bool => (int) $bundle->id === (int) ($data['vm_bundle_id'] ?? 0));
        $minimumCreateBalance = $bundle ? $this->minimumCreateBalance($bundle) : 0;

        if (! $quota['can_create']) {
            return back()
                ->withInput($this->safeCreateInput($request))
                ->with('error', $quota['message'] ?? 'ساخت VPS برای این حساب فعلا مجاز نیست.');
        }

        if ($wallet->is_locked) {
            return back()
                ->withInput($this->safeCreateInput($request))
                ->with('error', $wallet->lock_reason ?: 'کیف پول برای ثبت درخواست ساخت VPS قفل است.');
        }

        if ($bundle && $wallet->balance < $minimumCreateBalance) {
            return back()
                ->withInput($this->safeCreateInput($request))
                ->with('error', 'برای ساخت VPS موجودی کیف پول باید حداقل '.$this->wallets->format($minimumCreateBalance).' باشد.');
        }

        try {
            $result = $this->cloudProvisioning->create($customer, $data, project: $activeProject);
            $creationCharge = $bundle ? AppSetting::vmCreationChargeAmount((int) $bundle->monthly_price) : 0;

            if ($creationCharge > 0) {
                $this->wallets->charge($activeProject->owner, $creationCharge, 'هزینه اولیه ساخت VPS '.$result['vm']->name, $result['vm'], [
                    'category' => 'vm_creation_fee',
                    'percentage' => AppSetting::vmCreationChargePercentage(),
                    'monthly_price' => (int) $bundle->monthly_price,
                ]);
            }

            $status = $result['vm']->ip_address
                ? 'درخواست ساخت VPS ثبت شد. IP: '.$result['vm']->ip_address
                : 'درخواست ساخت VPS ثبت شد. آماده سازی در پس زمینه شروع شد.';

            return redirect()
                ->route('customer.servers.index')
                ->with('status', $status)
                ->with('provisioning_password', $result['password']);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput($this->safeCreateInput($request));
        } catch (Throwable $exception) {
            return back()
                ->withInput($this->safeCreateInput($request))
                ->with('error', 'ساخت VPS ممکن نیست: '.$exception->getMessage());
        }
    }

    public function show(Request $request, VirtualMachine $virtualMachine): View
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine);
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
            'activeProject' => $server->project,
            'activeMembership' => $this->projects->membership($server->project, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'server' => $server,
            'rebuildFee' => $this->rebuildFee($server),
            'billing' => $this->billing,
            'monthlyCost' => $monthlyCost,
            'sshCommand' => $sshCommand,
            'statusLabel' => $this->statusLabel($server->status),
            'statusClass' => $this->statusClass($server->status),
            'provisioningLabel' => $this->provisioningLabelForVm($server),
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

    public function rebuild(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine, manage: true);
        $server->loadMissing(['cloudImage', 'proxmoxServer', 'upgradeOrders', 'bundle', 'project.owner', 'customer']);

        if ($server->isActionLocked() || $server->provisioning_status === VirtualMachine::PROVISION_PENDING) {
            return back()->with('error', 'این سرور در حال انجام عملیات دیگری است و فعلا قابل بازسازی نیست.');
        }

        if ($server->upgradeOrders->contains(fn ($order): bool => $order->isPending())) {
            return back()->with('error', 'تا پایان ارتقای در حال انجام، بازسازی سرور ممکن نیست.');
        }

        if (! $server->proxmoxServer || ! $server->cloudImage || ! $server->node || ! $server->vmid || ! $server->template_vmid) {
            return back()->with('error', 'اطلاعات Proxmox، Image، Node، VMID یا Template برای بازسازی کامل نیست.');
        }

        if (! $server->cloudImage->is_active) {
            return back()->with('error', 'Image فعلی این سرور غیرفعال است و برای بازسازی قابل استفاده نیست.');
        }

        $billingCustomer = $server->project?->owner ?? $server->customer;
        $rebuildFee = $this->rebuildFee($server);

        if ($billingCustomer && $rebuildFee > 0) {
            $wallet = $this->wallets->walletFor($billingCustomer);

            if ($wallet->is_locked) {
                return back()->with('error', $wallet->lock_reason ?: 'کیف پول برای ثبت درخواست بازسازی VPS قفل است.');
            }

            if ($wallet->balance < $rebuildFee) {
                return back()->with('error', 'برای بازسازی VPS موجودی کیف پول باید حداقل '.$this->wallets->format($rebuildFee).' باشد.');
            }
        }

        $data = $request->validate([
            'rebuild_confirmation' => ['required', 'string', Rule::in([$server->name])],
            'hostname' => ['nullable', 'string', 'max:255'],
            'login_username' => ['nullable', 'string', 'max:64'],
            'login_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'ssh_public_key' => ['nullable', 'string', 'max:5000', $this->sshPublicKeyRule()],
        ], [
            'rebuild_confirmation.in' => 'برای بازسازی، نام سرور را دقیقا وارد کنید.',
        ]);

        $cloudInitEnabled = (bool) $server->cloudImage->cloud_init_enabled;
        $password = null;

        if ($cloudInitEnabled) {
            $password = $this->rebuildPassword($server, $data);
            $username = trim((string) ($data['login_username'] ?? '')) ?: ($server->login_username ?: $server->cloudImage->default_username);
            $hostname = trim((string) ($data['hostname'] ?? '')) ?: ($server->hostname ?: $server->name);
            $sshPublicKey = trim((string) ($data['ssh_public_key'] ?? ''));

            $server->forceFill([
                'hostname' => $hostname,
                'login_username' => $username,
                'login_password' => $password,
                'ssh_public_key' => $sshPublicKey !== '' ? $sshPublicKey : null,
            ]);
        }

        $this->usageBilling->chargeVm($server);

        if ($billingCustomer && $rebuildFee > 0) {
            $this->wallets->charge($billingCustomer, $rebuildFee, 'هزینه بازسازی VPS '.$server->name, $server, [
                'category' => 'vm_rebuild_fee',
                'creation_charge_percentage' => AppSetting::vmCreationChargePercentage(),
                'rebuild_multiplier_percentage' => AppSetting::vmRebuildFeeMultiplierPercentage(),
                'monthly_price' => (int) ($server->bundle?->monthly_price ?? 0),
            ], allowNegative: false);
        }

        $server->forceFill([
            'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_PENDING,
            'provisioning_task_id' => null,
            'remote_state' => array_merge($server->remote_state ?? [], [
                'rebuild_requested_at' => now()->toISOString(),
                'rebuild_started_at' => now()->toISOString(),
                'rebuild_finished_at' => null,
                'rebuild_failed_at' => null,
                'rebuild_error' => null,
                'rebuild_steps' => [],
            ]),
        ])->save();

        RebuildCloudVirtualMachine::dispatch($server->id)->onQueue(RebuildCloudVirtualMachine::QUEUE);

        $redirect = redirect()
            ->route('customer.servers.show', $server)
            ->with('status', 'درخواست بازسازی ثبت شد. وضعیت همین صفحه به‌روزرسانی می‌شود.');

        if ($cloudInitEnabled && $password !== null) {
            $redirect->with('provisioning_password', $password);
        }

        return $redirect;
    }

    public function destroy(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $server = $this->projects->resolveCustomerVm($request, $virtualMachine, manage: true);
        $server->loadMissing(['reservedIpAddress', 'proxmoxServer', 'customer', 'bundle']);
        $request->validate([
            'delete_confirmation' => ['required', 'string', Rule::in([$server->name])],
        ], [
            'delete_confirmation.in' => 'برای حذف، نام سرور را دقیقا وارد کنید.',
        ]);

        try {
            $result = $this->deletions->requestDelete($server, 'customer');
        } catch (Throwable $exception) {
            return back()->with('error', 'درخواست حذف سرور ثبت نشد: '.$exception->getMessage());
        }

        if ($result['status'] === 'already_queued') {
            return redirect()
                ->route('customer.servers.index')
                ->with('status', 'این سرور قبلا وارد صف حذف شده است.');
        }

        if ($result['finalized']) {
            return redirect()
                ->route('customer.servers.index')
                ->with('status', 'سرور در Proxmox پیدا نشد یا اتصال آن کامل نبود؛ رکورد پنل پاک شد، IP آزاد شد و Billing متوقف شد.');
        }

        return redirect()
            ->route('customer.servers.index')
            ->with('status', 'درخواست حذف سرور ثبت شد. Billing متوقف شد و وضعیت حذف از همین صفحه به‌روزرسانی می‌شود.');
    }

    private function osFamilies($cloudImages): array
    {
        $labels = [
            'ubuntu' => 'Ubuntu',
            'debian' => 'Debian',
            'rocky' => 'Rocky Linux',
            'router_os' => 'RouterOS',
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

    private function minimumCreateBalance(VmBundle $bundle): int
    {
        return max(
            (int) ceil($bundle->monthly_price / 2),
            AppSetting::vmCreationChargeAmount((int) $bundle->monthly_price),
        );
    }

    private function rebuildFee(VirtualMachine $server): int
    {
        $monthlyPrice = (int) ($server->bundle?->monthly_price ?? 0);

        return $monthlyPrice > 0 ? AppSetting::vmRebuildFeeAmount($monthlyPrice) : 0;
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

    private function provisioningLabelForVm(VirtualMachine $server): string
    {
        if ($this->isRebuilding($server)) {
            return 'در حال بازسازی';
        }

        if ($server->provisioning_status === VirtualMachine::PROVISION_FAILED && data_get($server->remote_state, 'rebuild_error')) {
            return 'بازسازی ناموفق';
        }

        return $this->provisioningLabel($server->provisioning_status);
    }

    private function isRebuilding(VirtualMachine $server): bool
    {
        return $server->provisioning_status === VirtualMachine::PROVISION_PENDING
            && filled(data_get($server->remote_state, 'rebuild_started_at'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rebuildPassword(VirtualMachine $server, array $data): ?string
    {
        $requestedPassword = trim((string) Arr::get($data, 'login_password', ''));

        if ($requestedPassword !== '') {
            return $requestedPassword;
        }

        if (filled($server->login_password)) {
            return $server->login_password;
        }

        if (filled((string) Arr::get($data, 'ssh_public_key', ''))) {
            return null;
        }

        return Str::password(18);
    }

    /**
     * @return array<string, mixed>
     */
    private function safeCreateInput(Request $request): array
    {
        return $request->except(['login_password', 'login_password_confirmation', 'name', 'hostname']);
    }

    private function sshPublicKeyRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $lines = collect(preg_split('/\R/', str_replace("\r\n", "\n", trim((string) $value))) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->values();

            foreach ($lines as $line) {
                if (! $this->isValidOpenSshPublicKey($line)) {
                    $fail('کلید SSH باید با فرمت OpenSSH public key وارد شود.');

                    return;
                }
            }
        };
    }

    private function isValidOpenSshPublicKey(string $line): bool
    {
        $parts = preg_split('/\s+/', trim($line), 3);
        $type = $parts[0] ?? '';
        $encoded = $parts[1] ?? '';

        if (! in_array($type, [
            'ssh-ed25519',
            'ssh-rsa',
            'ecdsa-sha2-nistp256',
            'ecdsa-sha2-nistp384',
            'ecdsa-sha2-nistp521',
            'sk-ssh-ed25519@openssh.com',
            'sk-ecdsa-sha2-nistp256@openssh.com',
        ], true)) {
            return false;
        }

        if ($encoded === '' || ! preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $encoded)) {
            return false;
        }

        $blob = base64_decode($encoded, true);

        if ($blob === false || strlen($blob) < 8) {
            return false;
        }

        $length = unpack('N', substr($blob, 0, 4))[1] ?? 0;

        if ($length <= 0 || strlen($blob) < 4 + $length) {
            return false;
        }

        return substr($blob, 4, $length) === $type;
    }
}

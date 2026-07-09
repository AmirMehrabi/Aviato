<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionCloudVirtualMachine;
use App\Models\CloudImage;
use App\Models\CloudImageNodeMapping;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\Project;
use App\Models\ProxmoxServer;
use App\Models\UsageAccrual;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\BillingService;
use App\Services\CloudVmProvisioningService;
use App\Services\HetznerCloudService;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use App\Services\UsageBalanceService;
use App\Services\VirtualMachineDeletionService;
use App\Services\VirtualMachineIpReassignmentService;
use App\Services\VmTransferService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class VirtualMachineController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly ProxmoxService $proxmox,
        private readonly CloudVmProvisioningService $cloudProvisioning,
        private readonly IpPoolService $ipPools,
        private readonly VirtualMachineDeletionService $deletions,
        private readonly VmTransferService $vmTransferService,
        private readonly WalletService $wallets,
        private readonly HetznerCloudService $hetzner,
        private readonly VirtualMachineIpReassignmentService $ipReassignments,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'status' => ['nullable', Rule::in([
                VirtualMachine::STATUS_RUNNING,
                VirtualMachine::STATUS_STOPPED,
                VirtualMachine::STATUS_SUSPENDED,
                VirtualMachine::STATUS_DELETING,
                VirtualMachine::STATUS_DELETED,
            ])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $vms = VirtualMachine::query()
            ->notDeleted()
            ->with(['customer', 'project.owner', 'creator', 'proxmoxServer', 'infrastructureLocation', 'bundle', 'cloudImage'])
            ->when($filters['customer_id'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['project_id'] ?? null, fn ($query, int $projectId) => $query->where('project_id', $projectId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('hostname', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.virtual-machines.index', [
            'vms' => $vms,
            'filters' => $filters,
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'projects' => Project::query()->orderBy('name')->pluck('name', 'id'),
            'proxmoxServers' => ProxmoxServer::query()->where('is_active', true)->orderBy('name')->get(),
            'billing' => $this->billing,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.virtual-machines.create', [
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'projects' => Project::query()->with('owner')->orderBy('name')->get(),
            'servers' => ProxmoxServer::query()
                ->where('is_active', true)
                ->where('maintenance_mode', false)
                ->orderBy('datacenter')
                ->orderBy('name')
                ->get(),
            'cloudImages' => CloudImage::query()
                ->with(['proxmoxServer', 'allowedBundles'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'bundles' => VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get(),
            'selectedCustomerId' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function options(ProxmoxServer $proxmoxServer): JsonResponse
    {
        try {
            return response()->json($this->proxmox->vmCreationOptions($proxmoxServer));
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to fetch Proxmox creation options.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedForCloud($request);
        $customer = Customer::findOrFail($data['customer_id']);
        $project = $this->resolveProjectForCustomer($customer, $data['project_id'] ?? null);
        $image = CloudImage::query()
            ->where('is_active', true)
            ->with('allowedBundles')
            ->findOrFail($data['cloud_image_id']);

        if (! empty($data['vm_bundle_id']) && ! $image->allowedBundles->contains(fn ($bundle): bool => (int) $bundle->id === (int) $data['vm_bundle_id'])) {
            return back()
                ->withErrors([
                    'vm_bundle_id' => 'این پلن برای این Cloud Image مجاز نیست.',
                ])
                ->withInput($request->except('login_password'));
        }

        if (! empty($data['proxmox_server_id']) && (int) $data['proxmox_server_id'] !== (int) $image->proxmox_server_id) {
            return back()
                ->withErrors([
                    'cloud_image_id' => 'این OS Template به Proxmox انتخاب شده تعلق ندارد.',
                ])
                ->withInput($request->except('login_password'));
        }

        try {
            $result = $this->cloudProvisioning->create($customer, $data, project: $project);
            $vm = $result['vm'];
            $message = 'Cloud VM provisioning queued. IP: '.$vm->ip_address.'.';

            return redirect()->route('admin.virtual-machines.show', $vm)
                ->with('status', $message)
                ->with('provisioning_password', $result['password']);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput($request->except('login_password'));
        } catch (Throwable $exception) {
            return back()
                ->withInput($request->except('login_password'))
                ->with('error', 'Cloud VM provisioning could not be queued: '.$exception->getMessage());
        }
    }

    public function show(VirtualMachine $virtualMachine): View
    {
        abort_if($virtualMachine->isDeleted(), 404);

        $virtualMachine->load([
            'customer',
            'project.owner',
            'creator',
            'proxmoxServer',
            'infrastructureLocation.hetznerAccount',
            'bundle',
            'cloudImage',
            'disks',
            'upgradeOrders' => fn ($query) => $query->with(['toBundle', 'disk'])->latest()->limit(10),
        ]);
        $billingCustomer = $virtualMachine->project?->owner ?? $virtualMachine->customer;

        $currentMonthStart = now()->startOfMonth();
        $currentMonthUsage = UsageAccrual::query()
            ->where('virtual_machine_id', $virtualMachine->id)
            ->where('service_date', '>=', $currentMonthStart)
            ->sum('amount');

        $totalUsage = UsageAccrual::query()
            ->where('virtual_machine_id', $virtualMachine->id)
            ->sum('amount');

        $currentAccrued = $this->billing->currentAccrued($virtualMachine);

        return view('admin.virtual-machines.show', [
            'vm' => $virtualMachine,
            'billing' => $this->billing,
            'wallet' => $billingCustomer ? $this->wallets->walletFor($billingCustomer) : null,
            'effectiveWalletBalance' => $billingCustomer
                ? app(UsageBalanceService::class)->effectiveBalance($billingCustomer)
                : null,
            'wallets' => $this->wallets,
            'billingCustomer' => $billingCustomer,
            'currentMonthUsage' => $currentMonthUsage,
            'totalUsage' => $totalUsage,
            'currentAccrued' => $currentAccrued,
        ]);
    }

    public function retryProvisioning(VirtualMachine $virtualMachine): RedirectResponse
    {
        $virtualMachine->loadMissing(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool', 'infrastructureLocation.hetznerAccount']);

        if ($virtualMachine->provisioning_status !== VirtualMachine::PROVISION_FAILED) {
            return back()->with('error', 'Only failed provisioning jobs can be retried.');
        }

        if (! $virtualMachine->proxmoxServer || ! $virtualMachine->cloudImage || ! $virtualMachine->node || ! $virtualMachine->template_vmid) {
            if (! $virtualMachine->isHetzner()) {
                return back()->with('error', 'This VM is missing Proxmox, image, node, or template data and cannot be retried.');
            }
        }

        try {
            if ($virtualMachine->isHetzner()) {
                if (! $virtualMachine->infrastructureLocation?->hetznerAccount || ! $virtualMachine->cloudImage) {
                    return back()->with('error', 'This VM is missing Hetzner account or image data and cannot be retried.');
                }

                $virtualMachine->forceFill([
                    'remote_id' => null,
                    'provisioning_status' => VirtualMachine::PROVISION_PENDING,
                    'provisioning_task_id' => null,
                    'remote_state' => array_merge($virtualMachine->remote_state ?? [], [
                        'retry_queued_at' => now()->toISOString(),
                    ]),
                ])->save();

                ProvisionCloudVirtualMachine::dispatch($virtualMachine->id, [
                    'start_after_create' => (bool) data_get($virtualMachine->desired_state, 'start_after_create', true),
                    'onboot' => false,
                ])->onQueue(ProvisionCloudVirtualMachine::QUEUE);

                return back()->with('status', 'Hetzner provisioning retry queued.');
            }

            if ($this->remoteVmMatchesPanelVm($virtualMachine)) {
                if ($virtualMachine->reservedIpAddress) {
                    $this->ipPools->assign($virtualMachine->reservedIpAddress, $virtualMachine);
                }

                $virtualMachine->forceFill([
                    'status' => VirtualMachine::STATUS_RUNNING,
                    'provisioning_status' => VirtualMachine::PROVISION_READY,
                    'last_seen_at' => now(),
                    'remote_state' => array_merge($virtualMachine->remote_state ?? [], [
                        'synced_from_retry_at' => now()->toISOString(),
                    ]),
                ])->save();

                return back()->with('status', 'Existing Proxmox VM matched this panel VM and was synced.');
            }

            $address = $virtualMachine->reservedIpAddress;
            $hasUsableAddress = $address
                && (int) $address->virtual_machine_id === (int) $virtualMachine->id
                && in_array($address->status, [IpAddress::STATUS_RESERVED, IpAddress::STATUS_ASSIGNED], true);

            $virtualMachine->forceFill([
                'vmid' => null,
                'provisioning_status' => VirtualMachine::PROVISION_PENDING,
                'provisioning_task_id' => null,
                'remote_state' => array_merge($virtualMachine->remote_state ?? [], [
                    'retry_queued_at' => now()->toISOString(),
                ]),
            ])->save();

            if (! $hasUsableAddress) {
                $virtualMachine->forceFill([
                    'ip_address_id' => null,
                    'ip_address' => null,
                ])->save();

                $remoteAddresses = $this->proxmox->assignedGuestIpAddresses($virtualMachine->proxmoxServer, $virtualMachine->node);
                $this->ipPools->reserveForVm($virtualMachine->refresh(), $remoteAddresses);
            }

            ProvisionCloudVirtualMachine::dispatch($virtualMachine->id, [
                'start_after_create' => (bool) data_get($virtualMachine->desired_state, 'start_after_create', true),
                'onboot' => (bool) data_get($virtualMachine->desired_state, 'onboot', false),
            ])->onQueue(ProvisionCloudVirtualMachine::QUEUE);

            return back()->with('status', 'Provisioning retry queued. The VMID will be recalculated before cloning.');
        } catch (Throwable $exception) {
            $virtualMachine->forceFill(['provisioning_status' => VirtualMachine::PROVISION_FAILED])->save();

            return back()->with('error', 'Provisioning retry could not be queued: '.$exception->getMessage());
        }
    }

    public function edit(VirtualMachine $virtualMachine): View
    {
        abort_if($virtualMachine->isDeleted(), 404);

        return view('admin.virtual-machines.edit', $this->formData($virtualMachine));
    }

    public function update(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        if ($virtualMachine->isActionLocked()) {
            return back()->with('error', 'این VM در وضعیت حذف است و قابل ویرایش نیست.');
        }

        $data = $this->validated($request, $virtualMachine);
        $selectedIpAddressId = $data['ip_address_id'] ?? null;
        $syncToProxmox = (bool) ($data['sync_to_proxmox'] ?? true);
        unset($data['ip_pool_id'], $data['ip_address_id'], $data['sync_to_proxmox']);

        $previousIpAddressId = $virtualMachine->ip_address_id;

        $virtualMachine->fill($data);
        if ($virtualMachine->project_id) {
            $virtualMachine->loadMissing('project');
            $virtualMachine->customer_id = $virtualMachine->project?->owner_customer_id ?? $virtualMachine->customer_id;
        }
        $this->applyBundleHardware($virtualMachine);
        $virtualMachine->desired_state = $virtualMachine->desiredStateSnapshot();
        $virtualMachine->save();

        if ($selectedIpAddressId && (int) $selectedIpAddressId !== (int) $previousIpAddressId) {
            try {
                $this->ipReassignments->reassign(
                    $virtualMachine,
                    IpAddress::query()->findOrFail((int) $selectedIpAddressId),
                    syncToProxmox: $syncToProxmox,
                );
            } catch (Throwable $exception) {
                return redirect()
                    ->route('admin.virtual-machines.show', $virtualMachine)
                    ->with('error', 'تغییر IP کامل نشد و بازگردانی انجام شد: '.$exception->getMessage());
            }
        }

        return redirect()->route('admin.virtual-machines.show', $virtualMachine)->with('status', 'VM به‌روزرسانی شد.');
    }

    public function updateIpAddress(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $data = $request->validate([
            'ip_address_id' => ['required', 'integer', 'exists:ip_addresses,id'],
            'sync_to_proxmox' => ['sometimes', 'boolean'],
        ]);

        $syncToProxmox = (bool) ($data['sync_to_proxmox'] ?? true);

        try {
            $this->ipReassignments->reassign(
                $virtualMachine,
                IpAddress::query()->findOrFail($data['ip_address_id']),
                syncToProxmox: $syncToProxmox,
            );
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = $syncToProxmox
            ? 'IP ماشین و تنظیمات Proxmox با موفقیت به‌روزرسانی شد.'
            : 'IP ماشین در دیتابیس به‌روزرسانی شد (بدون اعمال در Proxmox).';

        return back()->with('status', $message);
    }

    public function moveNode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vm_ids' => ['required', 'array', 'min:1'],
            'vm_ids.*' => ['integer', 'exists:virtual_machines,id'],
            'target_node' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in(['reconcile_only', 'migrate'])],
            'online' => ['sometimes', 'boolean'],
        ]);

        $targetNode = trim((string) $data['target_node']);
        $mode = (string) $data['mode'];
        $online = $request->boolean('online');
        $adminId = $request->user('admin')?->getAuthIdentifier();

        $vms = VirtualMachine::query()
            ->whereIn('id', $data['vm_ids'])
            ->with(['proxmoxServer', 'cloudImageNodeMapping'])
            ->get();

        $migrated = 0;
        $reconciled = 0;
        $skipped = [];

        foreach ($vms as $vm) {
            try {
                $result = $this->moveVmToNode($vm, $targetNode, $mode, $online, $adminId);

                if ($result === 'migrated') {
                    $migrated++;
                } elseif ($result === 'reconciled') {
                    $reconciled++;
                }
            } catch (Throwable $exception) {
                $skipped[] = '#'.$vm->id.' '.$vm->name.': '.$exception->getMessage();
            }
        }

        $message = sprintf('%d VM migrated, %d VM reconciled.', $migrated, $reconciled);

        return back()
            ->with('status', $message)
            ->with('error', $skipped === [] ? null : 'Skipped: '.implode(' | ', $skipped));
    }

    private function moveVmToNode(VirtualMachine $vm, string $targetNode, string $mode, bool $online, mixed $adminId): string
    {
        $vm->loadMissing('proxmoxServer');

        if ($vm->isActionLocked()) {
            throw new \RuntimeException('VM is deleting or deleted.');
        }

        if (! $vm->isProxmox() || ! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
            throw new \RuntimeException('VM is missing Proxmox server, source node, or VMID.');
        }

        if ((string) $vm->node === $targetNode) {
            throw new \RuntimeException('VM is already assigned to '.$targetNode.'.');
        }

        $server = $vm->proxmoxServer;
        $activeNodes = collect(data_get($server->remote_inventory, 'nodes', []))
            ->map(fn (array $node): ?string => $node['node'] ?? $node['name'] ?? null)
            ->filter()
            ->values();

        if (! $activeNodes->contains($targetNode)) {
            throw new \RuntimeException('Target node '.$targetNode.' is not present in the latest synced Proxmox inventory.');
        }

        $sourceNode = (string) $vm->node;
        $vmid = (int) $vm->vmid;
        $targetStatus = $this->vmStatusForNodeMove($server, $targetNode, $vmid, $vm);
        $sourceStatus = $this->vmStatusForNodeMove($server, $sourceNode, $vmid, $vm);

        if ($sourceStatus && $targetStatus) {
            throw new \RuntimeException('VMID exists on both source and target nodes; resolve duplicate remote state first.');
        }

        if (! $sourceStatus && ! $targetStatus) {
            throw new \RuntimeException('VMID was not found on source or target node.');
        }

        $migration = null;
        $action = 'reconciled';

        if ($targetStatus) {
            if ($mode !== 'reconcile_only') {
                throw new \RuntimeException('VM already exists on target; use reconcile-only mode.');
            }
        } else {
            if ($mode !== 'migrate') {
                throw new \RuntimeException('VM is still on source; use migrate mode to move it in Proxmox first.');
            }

            $migration = $this->proxmox->migrateVm($server, $sourceNode, $targetNode, $vmid, $online, [
                'source' => 'admin_bulk_node_move',
                'virtual_machine_id' => $vm->id,
                'admin_id' => $adminId,
            ]);

            if (! empty($migration['task_id'])) {
                $this->proxmox->waitForTask($server, $sourceNode, (string) $migration['task_id'], 900);
            }

            $targetStatus = $this->vmStatusForNodeMove($server, $targetNode, $vmid, $vm);

            if (! $targetStatus) {
                throw new \RuntimeException('Proxmox migration finished but VM was not found on target node.');
            }

            $action = 'migrated';
        }

        $targetConfig = $this->proxmox->vmConfigOrNull($server, $targetNode, $vmid);
        $mappingId = $this->nodeMappingIdFor($vm, $targetNode);

        $vm->forceFill([
            'node' => $targetNode,
            'cloud_image_node_mapping_id' => $mappingId ?? $vm->cloud_image_node_mapping_id,
            'status' => ($targetStatus['status'] ?? null) === 'running'
                ? VirtualMachine::STATUS_RUNNING
                : (($targetStatus['status'] ?? null) === 'stopped' ? VirtualMachine::STATUS_STOPPED : $vm->status),
            'last_seen_at' => now(),
            'remote_state' => array_merge($vm->remote_state ?? [], [
                'node_move' => [
                    'action' => $action,
                    'from_node' => $sourceNode,
                    'to_node' => $targetNode,
                    'task_id' => $migration['task_id'] ?? null,
                    'admin_id' => $adminId,
                    'moved_at' => now()->toISOString(),
                    'target_status' => $targetStatus,
                    'target_config' => $targetConfig,
                ],
            ]),
        ]);
        $vm->desired_state = $vm->desiredStateSnapshot();
        $vm->save();

        return $action;
    }

    private function nodeMappingIdFor(VirtualMachine $vm, string $targetNode): ?int
    {
        if (! $vm->cloud_image_id || ! $vm->proxmox_server_id) {
            return null;
        }

        return CloudImageNodeMapping::query()
            ->where('cloud_image_id', $vm->cloud_image_id)
            ->where('proxmox_server_id', $vm->proxmox_server_id)
            ->where('node', $targetNode)
            ->value('id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vmStatusForNodeMove(ProxmoxServer $server, string $node, int $vmid, VirtualMachine $vm): ?array
    {
        try {
            return $this->proxmox->vmStatus($server, $node, $vmid);
        } catch (Throwable $exception) {
            Log::warning('Unable to verify VM status during admin node move', [
                'virtual_machine_id' => $vm->id,
                'proxmox_server_id' => $server->id,
                'node' => $node,
                'vmid' => $vmid,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function destroy(VirtualMachine $virtualMachine): RedirectResponse
    {
        $virtualMachine->loadMissing(['proxmoxServer', 'reservedIpAddress', 'customer', 'bundle']);

        if ($virtualMachine->isDeleted()) {
            return back()->with('status', 'این VM قبلا حذف شده است.');
        }

        try {
            $result = $this->deletions->requestDelete($virtualMachine, 'admin');
        } catch (Throwable $exception) {
            return back()->with('error', 'درخواست حذف VM ثبت نشد: '.$exception->getMessage());
        }

        if ($result['status'] === 'already_queued') {
            return back()->with('status', 'این VM قبلا وارد صف حذف شده است.');
        }

        if ($result['finalized']) {
            return redirect()->route('admin.virtual-machines.index')->with('status', 'VM در Proxmox پیدا نشد یا اتصال آن کامل نبود؛ رکورد پنل پاک شد، IP آزاد شد و Billing متوقف شد.');
        }

        return redirect()->route('admin.virtual-machines.index')->with('status', 'VM وارد صف حذف شد.');
    }

    public function start(VirtualMachine $virtualMachine): RedirectResponse
    {
        if ($virtualMachine->isActionLocked()) {
            return back()->with('error', 'این VM در وضعیت حذف است و امکان روشن کردن ندارد.');
        }

        $virtualMachine->loadMissing(['project.owner', 'customer']);
        $billingCustomer = $virtualMachine->project?->owner ?? $virtualMachine->customer;

        if ($billingCustomer && $this->wallets->isBelowNegativeThreshold($billingCustomer)) {
            return back()->with('error', 'این VM تا شارژ شدن کیف پول فضای کاری مربوطه قابل روشن کردن نیست.');
        }

        $accrued = $this->billing->currentAccrued($virtualMachine);

        if (! $virtualMachine->isRunning()) {
            if ($virtualMachine->isProxmox() && (! $virtualMachine->proxmoxServer || ! $virtualMachine->node || ! $virtualMachine->vmid)) {
                return back()->with('error', 'اطلاعات Proxmox، Node یا VMID برای روشن کردن کامل نیست.');
            }

            if ($virtualMachine->isHetzner() && (! $virtualMachine->infrastructureLocation?->hetznerAccount || ! $virtualMachine->remote_id)) {
                return back()->with('error', 'اطلاعات Hetzner برای روشن کردن کامل نیست.');
            }

            try {
                if ($virtualMachine->isHetzner()) {
                    $serverInfo = $this->hetzner->server($virtualMachine->infrastructureLocation->hetznerAccount, $virtualMachine->remote_id);
                    $remoteRunning = ($serverInfo['server']['status'] ?? '') === 'running';

                    if (! $remoteRunning) {
                        $start = $this->hetzner->powerOn($virtualMachine->infrastructureLocation->hetznerAccount, $virtualMachine->remote_id);
                        $this->hetzner->waitForAction($virtualMachine->infrastructureLocation->hetznerAccount, $start['action']['id'] ?? null, 180);
                    }
                } else {
                    $remoteStatus = $this->proxmox->vmStatus($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid);
                    $remoteRunning = ($remoteStatus['status'] ?? '') === 'running';

                    if (! $remoteRunning) {
                        $start = $this->proxmox->startVm(
                            $virtualMachine->proxmoxServer,
                            $virtualMachine->node,
                            (int) $virtualMachine->vmid,
                            [
                                'source' => 'admin_start',
                                'virtual_machine_id' => $virtualMachine->id,
                                'admin_id' => request()->user('admin')?->getAuthIdentifier(),
                                'ip' => request()->ip(),
                            ],
                        );

                        if (! empty($start['task_id'])) {
                            $this->proxmox->waitForTask($virtualMachine->proxmoxServer, $virtualMachine->node, (string) $start['task_id'], 180);
                        }

                        $postStartStatus = $this->proxmox->vmStatus($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid);

                        if (($postStartStatus['status'] ?? '') !== 'running') {
                            sleep(3);
                            $postStartStatus = $this->proxmox->vmStatus($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid);
                        }

                        if (($postStartStatus['status'] ?? '') !== 'running') {
                            return back()->with('error', 'VM پس از ارسال فرمان روشن شدن، در Proxmox اجرا نشد. وضعیت فعلی: '.($postStartStatus['status'] ?? 'ناشناخته').'. لطفاً پیکربندی VM را بررسی کنید.');
                        }
                    }
                }
            } catch (Throwable $exception) {
                return back()->with('error', 'روشن کردن VM ناموفق بود: '.$exception->getMessage());
            }
        }

        $virtualMachine->forceFill([
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_started_at' => now(),
            'last_billed_at' => now(),
            'unbilled_amount' => $accrued,
            'desired_state' => array_merge($virtualMachine->desired_state ?? [], [
                'status' => VirtualMachine::STATUS_RUNNING,
                'power_generation' => (int) data_get($virtualMachine->desired_state, 'power_generation', 0) + 1,
                'power_intent_at' => now()->toISOString(),
                'power_intent_source' => 'admin_start',
            ]),
        ])->save();

        return back()->with('status', 'VM روشن شد. از این لحظه CPU و RAM هم محاسبه می‌شوند.');
    }

    public function stop(VirtualMachine $virtualMachine): RedirectResponse
    {
        $expectedGeneration = request()->integer('power_generation');
        $currentGeneration = (int) data_get($virtualMachine->desired_state, 'power_generation', 0);

        if (! request()->has('power_generation') || $expectedGeneration !== $currentGeneration) {
            Log::warning('Stale admin VM stop request rejected', [
                'virtual_machine_id' => $virtualMachine->id,
                'vmid' => $virtualMachine->vmid,
                'admin_id' => request()->user('admin')?->getAuthIdentifier(),
                'expected_power_generation' => $expectedGeneration,
                'current_power_generation' => $currentGeneration,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return back()->with('error', 'این درخواست خاموش کردن قدیمی است و اجرا نشد. وضعیت سرور دوباره بررسی شد.');
        }

        if ($virtualMachine->isActionLocked()) {
            return back()->with('error', 'این VM در وضعیت حذف است و امکان خاموش کردن ندارد.');
        }

        if ($virtualMachine->isProxmox() && (! $virtualMachine->proxmoxServer || ! $virtualMachine->node || ! $virtualMachine->vmid)) {
            return back()->with('error', 'اطلاعات Proxmox، Node یا VMID برای خاموش کردن کامل نیست.');
        }

        if ($virtualMachine->isHetzner() && (! $virtualMachine->infrastructureLocation?->hetznerAccount || ! $virtualMachine->remote_id)) {
            return back()->with('error', 'اطلاعات Hetzner برای خاموش کردن کامل نیست.');
        }

        $accrued = $this->billing->currentAccrued($virtualMachine);

        try {
            if ($virtualMachine->isHetzner()) {
                $shutdown = $this->hetzner->shutdown($virtualMachine->infrastructureLocation->hetznerAccount, $virtualMachine->remote_id);
                $this->hetzner->waitForAction($virtualMachine->infrastructureLocation->hetznerAccount, $shutdown['action']['id'] ?? null, 180);
            } else {
                Log::info('Admin VM shutdown requested', [
                    'virtual_machine_id' => $virtualMachine->id,
                    'uuid' => $virtualMachine->uuid,
                    'vmid' => $virtualMachine->vmid,
                    'node' => $virtualMachine->node,
                    'admin_id' => request()->user('admin')?->getAuthIdentifier(),
                    'power_generation' => $currentGeneration,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
                $shutdown = $this->proxmox->shutdownVm(
                    $virtualMachine->proxmoxServer,
                    $virtualMachine->node,
                    (int) $virtualMachine->vmid,
                    context: [
                        'source' => 'admin_stop',
                        'virtual_machine_id' => $virtualMachine->id,
                        'admin_id' => request()->user('admin')?->getAuthIdentifier(),
                        'ip' => request()->ip(),
                    ],
                );

                if (! empty($shutdown['task_id'])) {
                    $this->proxmox->waitForTask($virtualMachine->proxmoxServer, $virtualMachine->node, (string) $shutdown['task_id'], 180);
                }

                $this->proxmox->waitForVmStopped($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid, 60);
            }
        } catch (Throwable $exception) {
            return back()->with('error', 'خاموش کردن VM ناموفق بود: '.$exception->getMessage());
        }

        $virtualMachine->forceFill([
            'status' => VirtualMachine::STATUS_STOPPED,
            'last_stopped_at' => now(),
            'last_billed_at' => now(),
            'unbilled_amount' => $accrued,
            'desired_state' => array_merge($virtualMachine->desired_state ?? [], [
                'status' => VirtualMachine::STATUS_STOPPED,
                'power_generation' => $currentGeneration + 1,
                'power_intent_at' => now()->toISOString(),
                'power_intent_source' => 'admin_stop',
            ]),
        ])->save();

        return back()->with('status', 'VM خاموش شد. فقط IP و Disk محاسبه می‌شوند.');
    }

    public function showTransferForm(VirtualMachine $virtualMachine): View
    {
        abort_if($virtualMachine->isDeleted(), 404);

        $virtualMachine->load(['customer', 'project', 'transfers.fromCustomer', 'transfers.toCustomer', 'transfers.initiatedBy']);

        return view('admin.virtual-machines.transfer', [
            'vm' => $virtualMachine,
            'customers' => Customer::query()
                ->with(['ownedProjects' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')])
                ->where('status', 'active')
                ->where('id', '!=', $virtualMachine->customer_id)
                ->orderBy('name')
                ->get(),
            'transfers' => $virtualMachine->transfers()->with(['fromCustomer', 'toCustomer', 'fromProject', 'toProject', 'initiatedBy'])->latest()->get(),
            'billing' => $this->billing,
        ]);
    }

    public function transfer(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $data = $request->validate([
            'to_customer_id' => ['required', 'integer', 'exists:customers,id'],
            'to_project_id' => ['required', 'integer', 'exists:projects,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm_transfer' => ['required', 'accepted'],
        ]);

        $toCustomer = Customer::findOrFail($data['to_customer_id']);
        $toProject = Project::query()
            ->where('owner_customer_id', $toCustomer->id)
            ->find($data['to_project_id']);

        if (! $toProject) {
            return back()
                ->withInput()
                ->withErrors(['to_project_id' => 'فضای کاری انتخاب‌شده برای این مشتری نیست.']);
        }

        if ($virtualMachine->customer_id === $toCustomer->id) {
            return back()->withErrors(['to_customer_id' => 'این ماشین همین حالا متعلق به این مشتری است.']);
        }

        try {
            $transfer = $this->vmTransferService->transferVm(
                $virtualMachine,
                $toCustomer,
                auth('admin')->id(),
                $data['notes'] ?? null,
                $toProject,
            );

            return redirect()
                ->route('admin.virtual-machines.show', $virtualMachine)
                ->with('status', "ماشین به {$toCustomer->name} و فضای کاری {$toProject->name} منتقل شد. شماره انتقال: {$transfer->id}");
        } catch (\Exception $exception) {
            return back()
                ->withInput()
                ->with('error', 'انتقال ناموفق بود: '.$exception->getMessage());
        }
    }

    private function remoteVmMatchesPanelVm(VirtualMachine $vm): bool
    {
        if (! $vm->vmid || ! $vm->proxmoxServer || ! $vm->node) {
            return false;
        }

        try {
            $config = $this->proxmox->vmConfig($vm->proxmoxServer, $vm->node, (int) $vm->vmid);
        } catch (Throwable) {
            return false;
        }

        $remoteName = trim((string) ($config['name'] ?? ''));

        return $remoteName !== '' && hash_equals($remoteName, $vm->name);
    }

    private function formData(VirtualMachine $vm): array
    {
        return [
            'vm' => $vm,
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'projects' => Project::query()->with('owner')->orderBy('name')->get(),
            'servers' => ProxmoxServer::query()->orderBy('name')->get(),
            'bundles' => VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get(),
            'ipPools' => IpPool::query()
                ->with('proxmoxServer')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'ipAddresses' => IpAddress::query()
                ->with(['pool.proxmoxServer'])
                ->where(function ($query) use ($vm): void {
                    $query->whereIn('status', [IpAddress::STATUS_AVAILABLE, IpAddress::STATUS_RELEASED])
                        ->orWhere('virtual_machine_id', $vm->id);
                })
                ->orderBy('address')
                ->get(),
        ];
    }

    private function validatedForCloud(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'cloud_image_id' => ['required', 'integer', 'exists:cloud_images,id'],
            'proxmox_server_id' => ['nullable', 'integer', 'exists:proxmox_servers,id'],
            'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'],
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:128'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'node' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'os_template' => ['nullable', 'string', 'max:255'],
            'login_username' => ['nullable', 'string', 'max:64'],
            'login_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'ssh_public_key' => ['nullable', 'string', 'max:5000'],
            'cpu_cores' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'disk_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'start_after_create' => ['nullable', 'boolean'],
            'onboot' => ['nullable', 'boolean'],
        ]);

        $data['start_after_create'] = $request->boolean('start_after_create', true);
        $data['onboot'] = $request->boolean('onboot');

        return $data;
    }

    private function validated(Request $request, ?VirtualMachine $vm = null): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'proxmox_server_id' => ['required', 'integer', 'exists:proxmox_servers,id'],
            'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'],
            'ip_pool_id' => ['nullable', 'integer', 'exists:ip_pools,id'],
            'ip_address_id' => [
                'nullable',
                'integer',
                Rule::exists('ip_addresses', 'id')->where(function ($query) use ($request, $vm): void {
                    $query->when($request->filled('ip_pool_id'), fn ($query) => $query->where('ip_pool_id', $request->integer('ip_pool_id')))
                        ->where(function ($query) use ($vm): void {
                            $query->whereIn('status', [IpAddress::STATUS_AVAILABLE, IpAddress::STATUS_RELEASED])
                                ->orWhere('virtual_machine_id', $vm?->id);
                        });
                }),
            ],
            'vmid' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:128'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'node' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'os_template' => ['nullable', 'string', 'max:255'],
            'iso_volume' => ['nullable', 'string', 'max:500'],
            'network_bridge' => ['nullable', 'string', 'max:255'],
            'ostype' => ['nullable', Rule::in(['l26', 'win11', 'win10', 'win8', 'win7', 'w2k22', 'w2k19', 'w2k16', 'other'])],
            'start_after_create' => ['nullable', 'boolean'],
            'onboot' => ['nullable', 'boolean'],
            'tax_exempt' => ['nullable', 'boolean'],
            'sync_to_proxmox' => ['sometimes', 'boolean'],
        ]);
    }

    private function applyBundleHardware(VirtualMachine $vm): void
    {
        if (! $vm->vm_bundle_id) {
            $vm->ip_count = $vm->ip_count ?? 1;

            return;
        }

        $bundle = VmBundle::findOrFail($vm->vm_bundle_id);
        $vm->cpu_cores = $bundle->cpu_cores;
        $vm->ram_gb = $bundle->ram_gb;
        $vm->disk_gb = $bundle->disk_gb;
        $vm->ip_count = $bundle->ip_count;
    }

    private function syncCloudInitNetwork(VirtualMachine $virtualMachine): void
    {
        $virtualMachine->loadMissing(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool']);

        if (! $virtualMachine->proxmoxServer || ! $virtualMachine->node || ! $virtualMachine->vmid) {
            throw new \RuntimeException('VM is missing Proxmox server, node, or VMID.');
        }

        if (! $virtualMachine->cloudImage || ! $virtualMachine->cloudImage->cloud_init_enabled) {
            throw new \RuntimeException('Cloud-init is not enabled for this VM image.');
        }

        if (! $virtualMachine->reservedIpAddress) {
            throw new \RuntimeException('No reserved IP address is attached to this VM.');
        }

        $config = $this->proxmox->configureCloudInit($virtualMachine->proxmoxServer, [
            'node' => $virtualMachine->node,
            'vmid' => (int) $virtualMachine->vmid,
            'cpu_cores' => $virtualMachine->cpu_cores,
            'ram_gb' => $virtualMachine->ram_gb,
            'login_username' => $virtualMachine->login_username,
            'login_password' => $virtualMachine->login_password,
            'ssh_public_key' => $virtualMachine->ssh_public_key,
            'ipconfig0' => $this->ipPools->ipConfig($virtualMachine->reservedIpAddress),
            'nameserver' => $this->ipPools->nameservers($virtualMachine->reservedIpAddress),
            'cicustom' => 'vendor=local:snippets/ubuntu-password-login.yml',
            'description' => 'Cloud-init network updated by Aviato admin panel',
        ]);

        if (! empty($config['task_id'])) {
            $this->proxmox->waitForTask($virtualMachine->proxmoxServer, $virtualMachine->node, (string) $config['task_id'], 180);
        }

        $cloudInit = $this->proxmox->regenerateCloudInit($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid);

        if (! empty($cloudInit['task_id'])) {
            $this->proxmox->waitForTask($virtualMachine->proxmoxServer, $virtualMachine->node, (string) $cloudInit['task_id'], 180);
        }

        $antiSpoofing = null;
        $verifiedMacAddress = null;

        try {
            $antiSpoofing = $this->proxmox->applyVmIpAntiSpoofing(
                $virtualMachine->proxmoxServer,
                $virtualMachine->node,
                (int) $virtualMachine->vmid,
                $virtualMachine->reservedIpAddress->address,
                'net0',
                $virtualMachine->network_bridge ?: 'vmbr1',
            );

            $verifiedConfig = $this->proxmox->vmConfig($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid);
            $verifiedMacAddress = filled($verifiedConfig['net0'] ?? null)
                ? $this->proxmox->macAddressFromNetworkDevice((string) $verifiedConfig['net0'])
                : null;
        } catch (Throwable $exception) {
            Log::warning('Proxmox VM anti-spoofing firewall rules could not be synced after admin IP change', [
                'virtual_machine_id' => $virtualMachine->id,
                'message' => $exception->getMessage(),
            ]);
        }

        $virtualMachine->forceFill([
            'mac_address' => ($antiSpoofing['mac_address'] ?? null) ?: $verifiedMacAddress ?: $virtualMachine->mac_address,
            'desired_state' => $virtualMachine->desiredStateSnapshot(),
            'remote_state' => array_merge($virtualMachine->remote_state ?? [], [
                'cloudinit_network_updated_at' => now()->toISOString(),
                'cloudinit_network_ip' => $virtualMachine->ip_address,
                'anti_spoofing' => $antiSpoofing,
            ]),
        ])->save();
    }

    private function resolveProjectForCustomer(Customer $customer, ?int $projectId): Project
    {
        if ($projectId) {
            return Project::query()
                ->where('owner_customer_id', $customer->id)
                ->findOrFail($projectId);
        }

        return $customer->ensureDefaultProject();
    }
}

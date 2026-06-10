<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionCloudVirtualMachine;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\Project;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\BillingService;
use App\Services\CloudVmProvisioningService;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use App\Services\VirtualMachineDeletionService;
use App\Services\VmTransferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with(['customer', 'project.owner', 'creator', 'proxmoxServer', 'bundle', 'cloudImage'])
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
        $virtualMachine->load([
            'customer',
            'project.owner',
            'creator',
            'proxmoxServer',
            'bundle',
            'cloudImage',
            'disks',
            'upgradeOrders' => fn ($query) => $query->with(['toBundle', 'disk'])->latest()->limit(10),
        ]);

        return view('admin.virtual-machines.show', [
            'vm' => $virtualMachine,
            'billing' => $this->billing,
        ]);
    }

    public function retryProvisioning(VirtualMachine $virtualMachine): RedirectResponse
    {
        $virtualMachine->loadMissing(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool']);

        if ($virtualMachine->provisioning_status !== VirtualMachine::PROVISION_FAILED) {
            return back()->with('error', 'Only failed provisioning jobs can be retried.');
        }

        if (! $virtualMachine->proxmoxServer || ! $virtualMachine->cloudImage || ! $virtualMachine->node || ! $virtualMachine->template_vmid) {
            return back()->with('error', 'This VM is missing Proxmox, image, node, or template data and cannot be retried.');
        }

        try {
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
        return view('admin.virtual-machines.edit', $this->formData($virtualMachine));
    }

    public function update(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        if ($virtualMachine->isActionLocked()) {
            return back()->with('error', 'این VM در وضعیت حذف است و قابل ویرایش نیست.');
        }

        $data = $this->validated($request, $virtualMachine);
        $selectedIpAddressId = $data['ip_address_id'] ?? null;
        unset($data['ip_pool_id'], $data['ip_address_id']);

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
            $address = IpAddress::query()->with('pool')->findOrFail($selectedIpAddressId);
            $this->ipPools->reserveSpecificForVm($address, $virtualMachine);
            $virtualMachine->refresh();
        }

        if ((int) $virtualMachine->ip_address_id !== (int) $previousIpAddressId) {
            try {
                $this->syncCloudInitNetwork($virtualMachine);
            } catch (Throwable $exception) {
                return redirect()
                    ->route('admin.virtual-machines.show', $virtualMachine)
                    ->with('error', 'IP در پنل تغییر کرد اما اعمال Cloud-init روی Proxmox ناموفق بود: '.$exception->getMessage());
            }
        }

        return redirect()->route('admin.virtual-machines.show', $virtualMachine)->with('status', 'VM به‌روزرسانی شد.');
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

        if (! $virtualMachine->proxmoxServer || ! $virtualMachine->node || ! $virtualMachine->vmid) {
            return back()->with('error', 'اطلاعات Proxmox، Node یا VMID برای روشن کردن کامل نیست.');
        }

        $accrued = $this->billing->currentAccrued($virtualMachine);

        try {
            $start = $this->proxmox->startVm($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid);

            if (! empty($start['task_id'])) {
                $this->proxmox->waitForTask($virtualMachine->proxmoxServer, $virtualMachine->node, (string) $start['task_id'], 180);
            }
        } catch (Throwable $exception) {
            return back()->with('error', 'روشن کردن VM در Proxmox ناموفق بود: '.$exception->getMessage());
        }

        $virtualMachine->forceFill([
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_started_at' => now(),
            'last_billed_at' => now(),
            'unbilled_amount' => $accrued,
            'desired_state' => array_merge($virtualMachine->desired_state ?? [], ['status' => VirtualMachine::STATUS_RUNNING]),
        ])->save();

        return back()->with('status', 'VM روشن شد. از این لحظه CPU و RAM هم محاسبه می‌شوند.');
    }

    public function stop(VirtualMachine $virtualMachine): RedirectResponse
    {
        if ($virtualMachine->isActionLocked()) {
            return back()->with('error', 'این VM در وضعیت حذف است و امکان خاموش کردن ندارد.');
        }

        if (! $virtualMachine->proxmoxServer || ! $virtualMachine->node || ! $virtualMachine->vmid) {
            return back()->with('error', 'اطلاعات Proxmox، Node یا VMID برای خاموش کردن کامل نیست.');
        }

        $accrued = $this->billing->currentAccrued($virtualMachine);

        try {
            $shutdown = $this->proxmox->shutdownVm($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid);

            if (! empty($shutdown['task_id'])) {
                $this->proxmox->waitForTask($virtualMachine->proxmoxServer, $virtualMachine->node, (string) $shutdown['task_id'], 180);
            }

            $this->proxmox->waitForVmStopped($virtualMachine->proxmoxServer, $virtualMachine->node, (int) $virtualMachine->vmid, 60);
        } catch (Throwable $exception) {
            return back()->with('error', 'خاموش کردن VM در Proxmox ناموفق بود: '.$exception->getMessage());
        }

        $virtualMachine->forceFill([
            'status' => VirtualMachine::STATUS_STOPPED,
            'last_stopped_at' => now(),
            'last_billed_at' => now(),
            'unbilled_amount' => $accrued,
            'desired_state' => array_merge($virtualMachine->desired_state ?? [], ['status' => VirtualMachine::STATUS_STOPPED]),
        ])->save();

        return back()->with('status', 'VM خاموش شد. فقط IP و Disk محاسبه می‌شوند.');
    }

    public function showTransferForm(VirtualMachine $virtualMachine): View
    {
        $virtualMachine->load(['customer', 'project', 'transfers.fromCustomer', 'transfers.toCustomer', 'transfers.initiatedBy']);

        return view('admin.virtual-machines.transfer', [
            'vm' => $virtualMachine,
            'customers' => Customer::query()
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm_transfer' => ['required', 'accepted'],
        ]);

        $toCustomer = Customer::findOrFail($data['to_customer_id']);

        if ($virtualMachine->customer_id === $toCustomer->id) {
            return back()->withErrors(['to_customer_id' => 'VM already belongs to this customer.']);
        }

        try {
            $transfer = $this->vmTransferService->transferVm(
                $virtualMachine,
                $toCustomer,
                auth()->id(),
                $data['notes'] ?? null
            );

            return redirect()
                ->route('admin.virtual-machines.show', $virtualMachine)
                ->with('status', "VM successfully transferred to {$toCustomer->name}. Transfer ID: {$transfer->id}");
        } catch (\Exception $exception) {
            return back()
                ->withInput()
                ->with('error', 'Transfer failed: '.$exception->getMessage());
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
            'hostname' => ['nullable', 'string', 'max:255'],
            'node' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'os_template' => ['nullable', 'string', 'max:255'],
            'iso_volume' => ['nullable', 'string', 'max:500'],
            'network_bridge' => ['nullable', 'string', 'max:255'],
            'ostype' => ['nullable', Rule::in(['l26', 'win11', 'win10', 'win8', 'win7', 'w2k22', 'w2k19', 'w2k16', 'other'])],
            'start_after_create' => ['nullable', 'boolean'],
            'onboot' => ['nullable', 'boolean'],
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

        $virtualMachine->forceFill([
            'mac_address' => $antiSpoofing['mac_address'] ?: $verifiedMacAddress,
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\BillingService;
use App\Services\CloudVmProvisioningService;
use App\Services\ProxmoxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class VirtualMachineController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly ProxmoxService $proxmox,
        private readonly CloudVmProvisioningService $cloudProvisioning,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'status' => ['nullable', Rule::in([VirtualMachine::STATUS_RUNNING, VirtualMachine::STATUS_STOPPED, VirtualMachine::STATUS_SUSPENDED])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $vms = VirtualMachine::query()
            ->with(['customer', 'proxmoxServer', 'bundle', 'cloudImage'])
            ->when($filters['customer_id'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
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
            'billing' => $this->billing,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.virtual-machines.create', [
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'servers' => ProxmoxServer::query()
                ->where('is_active', true)
                ->where('maintenance_mode', false)
                ->orderBy('datacenter')
                ->orderBy('name')
                ->get(),
            'cloudImages' => CloudImage::query()
                ->with('proxmoxServer')
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

        try {
            $result = $this->cloudProvisioning->create($customer, $data);
            $vm = $result['vm'];
            $message = 'Cloud VM provisioning queued. IP: '.$vm->ip_address.'.';

            return redirect()->route('admin.virtual-machines.show', $vm)
                ->with('status', $message)
                ->with('provisioning_password', $result['password']);
        } catch (Throwable $exception) {
            return back()
                ->withInput($request->except('login_password'))
                ->with('error', 'Cloud VM provisioning could not be queued: '.$exception->getMessage());
        }
    }

    public function show(VirtualMachine $virtualMachine): View
    {
        $virtualMachine->load(['customer', 'proxmoxServer', 'bundle', 'cloudImage']);

        return view('admin.virtual-machines.show', [
            'vm' => $virtualMachine,
            'billing' => $this->billing,
        ]);
    }

    public function edit(VirtualMachine $virtualMachine): View
    {
        return view('admin.virtual-machines.edit', $this->formData($virtualMachine));
    }

    public function update(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $virtualMachine->fill($this->validated($request, $virtualMachine));
        $this->applyBundleHardware($virtualMachine);
        $virtualMachine->desired_state = $virtualMachine->desiredStateSnapshot();
        $virtualMachine->save();

        return redirect()->route('admin.virtual-machines.show', $virtualMachine)->with('status', 'VM به‌روزرسانی شد.');
    }

    public function destroy(VirtualMachine $virtualMachine): RedirectResponse
    {
        $virtualMachine->delete();

        return redirect()->route('admin.virtual-machines.index')->with('status', 'VM حذف شد.');
    }

    public function start(VirtualMachine $virtualMachine): RedirectResponse
    {
        $accrued = $this->billing->currentAccrued($virtualMachine);

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
        $accrued = $this->billing->currentAccrued($virtualMachine);

        $virtualMachine->forceFill([
            'status' => VirtualMachine::STATUS_STOPPED,
            'last_stopped_at' => now(),
            'last_billed_at' => now(),
            'unbilled_amount' => $accrued,
            'desired_state' => array_merge($virtualMachine->desired_state ?? [], ['status' => VirtualMachine::STATUS_STOPPED]),
        ])->save();

        return back()->with('status', 'VM خاموش شد. فقط IP و Disk محاسبه می‌شوند.');
    }

    private function formData(VirtualMachine $vm): array
    {
        return [
            'vm' => $vm,
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'servers' => ProxmoxServer::query()->orderBy('name')->pluck('name', 'id'),
            'bundles' => VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get(),
        ];
    }

    private function validatedForCloud(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'cloud_image_id' => ['required', 'integer', 'exists:cloud_images,id'],
            'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'],
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
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
            'proxmox_server_id' => ['required', 'integer', 'exists:proxmox_servers,id'],
            'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'],
            'vmid' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'node' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'os_template' => ['nullable', 'string', 'max:255'],
            'iso_volume' => ['nullable', 'string', 'max:500'],
            'network_bridge' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'cpu_cores' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'disk_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'ip_count' => ['nullable', 'integer', 'min:0', 'max:128'],
            'status' => ['nullable', Rule::in([VirtualMachine::STATUS_RUNNING, VirtualMachine::STATUS_STOPPED, VirtualMachine::STATUS_SUSPENDED])],
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
}

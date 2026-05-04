<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\BillingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VirtualMachineController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'status' => ['nullable', Rule::in([VirtualMachine::STATUS_RUNNING, VirtualMachine::STATUS_STOPPED, VirtualMachine::STATUS_SUSPENDED])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $vms = VirtualMachine::query()
            ->with(['customer', 'proxmoxServer', 'bundle'])
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
        $vm = new VirtualMachine([
            'customer_id' => $request->integer('customer_id') ?: null,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 50,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_STOPPED,
        ]);

        return view('admin.virtual-machines.create', $this->formData($vm));
    }

    public function store(Request $request): RedirectResponse
    {
        $vm = VirtualMachine::make($this->validated($request));
        $this->applyBundleHardware($vm);
        $vm->last_billed_at = now();
        $vm->desired_state = $vm->desiredStateSnapshot();
        $vm->save();

        return redirect()->route('admin.virtual-machines.show', $vm)->with('status', 'VM ساخته شد و به مشتری متصل شد.');
    }

    public function show(VirtualMachine $virtualMachine): View
    {
        $virtualMachine->load(['customer', 'proxmoxServer', 'bundle']);

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

    private function validated(Request $request, ?VirtualMachine $vm = null): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'proxmox_server_id' => ['nullable', 'integer', 'exists:proxmox_servers,id'],
            'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'],
            'vmid' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'node' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'os_template' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'cpu_cores' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'disk_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'ip_count' => ['nullable', 'integer', 'min:0', 'max:128'],
            'status' => ['required', Rule::in([VirtualMachine::STATUS_RUNNING, VirtualMachine::STATUS_STOPPED, VirtualMachine::STATUS_SUSPENDED])],
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\ProxmoxServer;
use App\Models\VmBundle;
use App\Services\UnprovisionedVirtualMachineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class UnprovisionedVirtualMachineController extends Controller
{
    public function __construct(private readonly UnprovisionedVirtualMachineService $imports) {}

    public function index(Request $request): View
    {
        $server = $request->filled('server_id')
            ? ProxmoxServer::query()->where('is_active', true)->findOrFail($request->integer('server_id'))
            : null;

        return view('admin.unprovisioned-virtual-machines.index', [
            'candidates' => $this->imports->candidates($server),
            'servers' => ProxmoxServer::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedServerId' => $server?->id,
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'bundles' => VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get(),
            'ipAddresses' => IpAddress::query()->with(['pool.proxmoxServer'])
                ->whereIn('status', [IpAddress::STATUS_AVAILABLE, IpAddress::STATUS_RELEASED])
                ->orderBy('address')->get(),
        ]);
    }

    public function claim(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'proxmox_server_id' => ['required', 'integer', 'exists:proxmox_servers,id'],
            'vmid' => ['required', 'integer', 'min:1'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'vm_bundle_id' => ['required', 'integer', 'exists:vm_bundles,id'],
            'ip_address_id' => ['required', 'integer', 'exists:ip_addresses,id'],
        ]);

        try {
            $vm = $this->imports->claim(
                ProxmoxServer::query()->findOrFail($data['proxmox_server_id']),
                (int) $data['vmid'],
                (int) $data['customer_id'],
                (int) $data['vm_bundle_id'],
                (int) $data['ip_address_id'],
            );

            return redirect()->route('admin.virtual-machines.show', $vm)->with('status', 'The Proxmox guest was imported and assigned successfully.');
        } catch (Throwable $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }
}

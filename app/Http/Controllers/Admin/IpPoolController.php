<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IpPoolController extends Controller
{
    public function index(): View
    {
        return view('admin.ip-pools.index', [
            'pools' => IpPool::query()
                ->with(['proxmoxServer', 'addresses'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(IpPool $ipPool): View
    {
        $ipPool->load([
            'proxmoxServer',
            'addresses' => fn ($query) => $query
                ->with(['virtualMachine.customer', 'virtualMachine.bundle'])
                ->orderBy('address'),
        ]);

        return view('admin.ip-pools.show', [
            'pool' => $ipPool,
        ]);
    }

    public function create(): View
    {
        return view('admin.ip-pools.create', $this->formData(new IpPool([
            'network_bridge' => 'vmbr0',
            'prefix_length' => 24,
            'nameservers' => '1.1.1.1',
            'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        IpPool::create($this->validated($request));

        return redirect()->route('admin.ip-pools.index')->with('status', 'IP pool saved.');
    }

    public function edit(IpPool $ipPool): View
    {
        return view('admin.ip-pools.edit', $this->formData($ipPool));
    }

    public function update(Request $request, IpPool $ipPool): RedirectResponse
    {
        $ipPool->update($this->validated($request));

        return redirect()->route('admin.ip-pools.index')->with('status', 'IP pool updated.');
    }

    public function destroy(IpPool $ipPool): RedirectResponse
    {
        $ipPool->delete();

        return redirect()->route('admin.ip-pools.index')->with('status', 'IP pool deleted.');
    }

    private function formData(IpPool $pool): array
    {
        return [
            'pool' => $pool,
            'servers' => ProxmoxServer::query()->orderBy('datacenter')->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'proxmox_server_id' => ['required', 'integer', 'exists:proxmox_servers,id'],
            'name' => ['required', 'string', 'max:255'],
            'node' => ['nullable', 'string', 'max:255'],
            'network_bridge' => ['required', 'string', 'max:64'],
            'gateway' => ['required', 'ip'],
            'prefix_length' => ['required', 'integer', 'min:1', 'max:32'],
            'nameservers' => ['nullable', 'string', 'max:255'],
            'start_ip' => ['required', 'ip'],
            'end_ip' => ['nullable', 'ip'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

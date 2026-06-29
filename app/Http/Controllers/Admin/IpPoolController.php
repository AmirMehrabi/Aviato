<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Services\IpPoolService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IpPoolController extends Controller
{
    public function __construct(
        private readonly IpPoolService $ipPools,
    ) {}

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
        $this->loadInventory($ipPool);

        return view('admin.ip-pools.show', [
            'pool' => $ipPool,
        ]);
    }

    public function create(): View
    {
        return view('admin.ip-pools.create', $this->formData(new IpPool([
            'network_bridge' => 'vmbr1',
            'prefix_length' => 24,
            'nameservers' => '1.1.1.1',
            'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $ipPool = DB::transaction(function () use ($request): IpPool {
                $ipPool = IpPool::create($this->validated($request));
                $this->loadInventory($ipPool);

                return $ipPool;
            });
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_ip' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('admin.ip-pools.edit', $ipPool)
            ->with('status', 'IP pool saved. The inventory is ready for management.');
    }

    public function edit(IpPool $ipPool): View
    {
        $this->loadInventory($ipPool);

        return view('admin.ip-pools.edit', $this->formData($ipPool));
    }

    public function update(Request $request, IpPool $ipPool): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $ipPool): void {
                $ipPool->update($this->validated($request));
                $this->loadInventory($ipPool);
            });
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_ip' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('admin.ip-pools.edit', $ipPool)
            ->with('status', 'IP pool updated. Inventory and reserve actions remain available below.');
    }

    public function reserveAddresses(Request $request, IpPool $ipPool): RedirectResponse
    {
        $data = $request->validate([
            'address_ids' => ['required', 'array', 'min:1'],
            'address_ids.*' => ['required', 'integer'],
        ]);

        try {
            $reserved = $this->ipPools->reserveAddresses($ipPool, $data['address_ids']);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors([
                'reservation' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.ip-pools.edit', $ipPool)
            ->with('status', $this->reservationMessage($reserved->pluck('address')->all()));
    }

    public function reserveAddress(IpPool $ipPool, IpAddress $ipAddress): RedirectResponse
    {
        if ((int) $ipAddress->ip_pool_id !== (int) $ipPool->id) {
            abort(404);
        }

        try {
            $reserved = $this->ipPools->reserveAddresses($ipPool, [$ipAddress->id]);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'reservation' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.ip-pools.edit', $ipPool)
            ->with('status', $this->reservationMessage($reserved->pluck('address')->all()));
    }

    public function releaseAddress(IpPool $ipPool, IpAddress $ipAddress): RedirectResponse
    {
        if ((int) $ipAddress->ip_pool_id !== (int) $ipPool->id) {
            abort(404);
        }

        $this->ipPools->release($ipAddress);

        return redirect()
            ->route('admin.ip-pools.edit', $ipPool)
            ->with('status', 'آدرس IP '.$ipAddress->address.' آزاد شد.');
    }

    private function loadInventory(IpPool $ipPool): void
    {
        $this->ipPools->ensurePoolAddresses($ipPool);

        $ipPool->load([
            'proxmoxServer',
            'addresses' => fn ($query) => $query
                ->with(['virtualMachine.customer', 'virtualMachine.bundle'])
                ->orderBy('address'),
        ]);
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
            'rangePreview' => $this->rangePreview($pool),
            'inventory' => $this->inventoryStats($pool),
        ];
    }

    private function rangePreview(IpPool $pool): array
    {
        if (! $pool->start_ip) {
            return [
                'count' => 0,
                'first' => null,
                'last' => null,
                'range' => null,
            ];
        }

        $addresses = $this->ipPools->addressRange($pool->start_ip, $pool->end_ip);

        return [
            'count' => count($addresses),
            'first' => $addresses[0] ?? null,
            'last' => $addresses[array_key_last($addresses)] ?? null,
            'range' => $pool->end_ip ? $pool->start_ip.' - '.$pool->end_ip : $pool->start_ip,
        ];
    }

    private function inventoryStats(IpPool $pool): array
    {
        $addresses = $pool->relationLoaded('addresses') ? $pool->addresses : collect();

        return [
            'total' => $addresses->count(),
            'available' => $addresses->where('status', IpAddress::STATUS_AVAILABLE)->count(),
            'released' => $addresses->where('status', IpAddress::STATUS_RELEASED)->count(),
            'reserved' => $addresses->where('status', IpAddress::STATUS_RESERVED)->count(),
            'assigned' => $addresses->where('status', IpAddress::STATUS_ASSIGNED)->count(),
        ];
    }

    /**
     * @param  array<int, string>  $addresses
     */
    private function reservationMessage(array $addresses): string
    {
        if (count($addresses) === 1) {
            return 'IP address '.$addresses[0].' reserved.';
        }

        return count($addresses).' IP addresses reserved: '.implode(', ', $addresses).'.';
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

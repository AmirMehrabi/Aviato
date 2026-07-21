<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnprovisionedVirtualMachineService
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
        private readonly IpPoolService $ipPools,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function candidates(?ProxmoxServer $server = null): Collection
    {
        $servers = $server ? collect([$server]) : ProxmoxServer::query()->where('is_active', true)->get();

        return $servers->flatMap(function (ProxmoxServer $server): Collection {
            $claimed = VirtualMachine::query()
                ->where('proxmox_server_id', $server->id)
                ->whereNotNull('vmid')
                ->pluck('vmid')
                ->map(fn (mixed $vmid): int => (int) $vmid)
                ->all();

            return collect(data_get($server->remote_inventory, 'virtual_machines', []))
                ->filter(fn (mixed $guest): bool => is_array($guest) && isset($guest['vmid']))
                ->reject(fn (array $guest): bool => in_array((int) $guest['vmid'], $claimed, true))
                ->map(fn (array $guest): array => $this->normalize($server, $guest));
        })->values();
    }

    public function claim(
        ProxmoxServer $server,
        int $vmid,
        int $customerId,
        int $bundleId,
        int $ipAddressId,
    ): VirtualMachine {
        $remote = collect(data_get($this->proxmox->summary($server), 'virtual_machines', []))
            ->first(fn (mixed $guest): bool => is_array($guest) && (int) ($guest['vmid'] ?? 0) === $vmid);

        if (! is_array($remote)) {
            throw new RuntimeException('The selected guest was not found in the latest Proxmox inventory. Refresh the page and try again.');
        }

        $guest = $this->normalize($server, $remote);
        $customer = Customer::query()->findOrFail($customerId);
        $bundle = VmBundle::query()->where('is_active', true)->findOrFail($bundleId);
        $ipAddress = IpAddress::query()->with('pool')->findOrFail($ipAddressId);

        if ($bundle->cpu_cores < $guest['cpu_cores'] || $bundle->ram_gb < $guest['ram_gb'] || $bundle->disk_gb < $guest['disk_gb']) {
            throw new RuntimeException('The selected bundle does not provide enough CPU, memory, or disk for this guest.');
        }

        return DB::transaction(function () use ($server, $vmid, $customer, $bundle, $ipAddress, $guest): VirtualMachine {
            if (VirtualMachine::query()->where('proxmox_server_id', $server->id)->where('vmid', $vmid)->exists()) {
                throw new RuntimeException('This Proxmox guest has already been assigned in the panel.');
            }

            $project = $customer->ensureDefaultProject();
            $vm = VirtualMachine::create([
                'customer_id' => $customer->id,
                'project_id' => $project->id,
                'proxmox_server_id' => $server->id,
                'vm_bundle_id' => $bundle->id,
                'vmid' => $vmid,
                'name' => $guest['name'],
                'display_name' => $guest['name'],
                'hostname' => $guest['name'],
                'node' => $guest['node'],
                'cpu_cores' => $guest['cpu_cores'],
                'ram_gb' => $guest['ram_gb'],
                'disk_gb' => $guest['disk_gb'],
                'ip_count' => $bundle->ip_count,
                'status' => $guest['status'],
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'provider_metadata' => [
                    'imported_from_inventory' => true,
                    'guest_type' => $guest['guest_type'],
                    'imported_at' => now()->toISOString(),
                ],
                'remote_state' => $guest['raw'],
                'last_seen_at' => now(),
                'last_billed_at' => now(),
                'unbilled_amount' => 0,
            ]);

            $this->ipPools->reserveSpecificForVm($ipAddress, $vm);

            return $vm->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function normalize(ProxmoxServer $server, array $guest): array
    {
        $memory = max(1, (int) ceil(((int) ($guest['maxmem'] ?? $guest['mem'] ?? 0)) / 1073741824));
        $disk = max(1, (int) ceil(((int) ($guest['maxdisk'] ?? 0)) / 1073741824));
        $type = (string) ($guest['type'] ?? 'qemu');

        return [
            'server_id' => $server->id,
            'server_name' => $server->name,
            'vmid' => (int) $guest['vmid'],
            'name' => trim((string) ($guest['name'] ?? '')) ?: 'Proxmox guest '.$guest['vmid'],
            'node' => (string) ($guest['node'] ?? ''),
            'guest_type' => $type === 'lxc' ? 'lxc' : 'qemu',
            'status' => ($guest['status'] ?? null) === 'running' ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
            'cpu_cores' => max(1, (int) ($guest['maxcpu'] ?? $guest['cpus'] ?? 1)),
            'ram_gb' => $memory,
            'disk_gb' => $disk,
            'raw' => $guest,
        ];
    }
}

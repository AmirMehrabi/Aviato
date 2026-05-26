<?php

namespace App\Services;

use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IpPoolService
{
    /**
         * @param  array<int, string>  $excludedAddresses
     */
    public function reserveForVm(VirtualMachine $vm, array $excludedAddresses = []): IpAddress
    {
        $excludedAddresses = array_values(array_unique(array_filter($excludedAddresses)));

        return DB::transaction(function () use ($vm, $excludedAddresses): IpAddress {
            $pool = IpPool::query()
                ->where('proxmox_server_id', $vm->proxmox_server_id)
                ->where('is_active', true)
                ->where(function ($query) use ($vm): void {
                    $query->whereNull('node')->orWhere('node', $vm->node);
                })
                ->orderByRaw('node is null')
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                throw new RuntimeException('No active IP pool is available for this Proxmox server/node.');
            }

            $this->ensurePoolAddresses($pool);

            $address = $pool->addresses()
                ->whereIn('status', [IpAddress::STATUS_AVAILABLE, IpAddress::STATUS_RELEASED])
                ->when($excludedAddresses !== [], fn ($query) => $query->whereNotIn('address', $excludedAddresses))
                ->orderBy('address')
                ->lockForUpdate()
                ->first();

            if (! $address) {
                throw new RuntimeException('No available IP address remains in the selected pool.');
            }

            $address->forceFill([
                'virtual_machine_id' => $vm->id,
                'status' => IpAddress::STATUS_RESERVED,
                'reserved_at' => now(),
                'assigned_at' => null,
                'released_at' => null,
            ])->save();

            $vm->forceFill([
                'ip_address_id' => $address->id,
                'ip_address' => $address->address,
                'network_bridge' => $pool->network_bridge,
            ])->save();

            return $address;
        });
    }

    public function assign(IpAddress $address, VirtualMachine $vm): void
    {
        $address->forceFill([
            'virtual_machine_id' => $vm->id,
            'status' => IpAddress::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'released_at' => null,
        ])->save();
    }

    public function release(IpAddress $address): void
    {
        $address->forceFill([
            'virtual_machine_id' => null,
            'status' => IpAddress::STATUS_RELEASED,
            'reserved_at' => null,
            'assigned_at' => null,
            'released_at' => now(),
        ])->save();
    }

    public function ipConfig(IpAddress $address): string
    {
        $pool = $address->pool;

        return "ip={$address->address}/{$pool->prefix_length},gw={$pool->gateway}";
    }

    public function nameservers(IpAddress $address): string
    {
        return trim((string) $address->pool->nameservers) ?: '1.1.1.1';
    }

    private function ensurePoolAddresses(IpPool $pool): void
    {
        if ($pool->addresses()->exists()) {
            return;
        }

        $start = ip2long($pool->start_ip);
        $end = ip2long($pool->end_ip ?: $pool->start_ip);

        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('The selected IP pool has an invalid IP range.');
        }

        if (($end - $start) > 4096) {
            throw new RuntimeException('IP pools may generate at most 4096 addresses at a time.');
        }

        for ($ip = $start; $ip <= $end; $ip++) {
            $pool->addresses()->firstOrCreate(
                ['address' => long2ip($ip)],
                ['status' => IpAddress::STATUS_AVAILABLE],
            );
        }
    }
}

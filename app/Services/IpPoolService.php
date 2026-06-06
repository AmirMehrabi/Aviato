<?php

namespace App\Services;

use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\VirtualMachine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IpPoolService
{
    /**
     * @return array<int, string>
     */
    public function addressRange(string $startIp, ?string $endIp = null): array
    {
        $start = ip2long($startIp);
        $end = ip2long($endIp ?: $startIp);

        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('The selected IP pool has an invalid IP range.');
        }

        if (($end - $start) > 4096) {
            throw new RuntimeException('IP pools may generate at most 4096 addresses at a time.');
        }

        $addresses = [];

        for ($ip = $start; $ip <= $end; $ip++) {
            $addresses[] = long2ip($ip);
        }

        return $addresses;
    }

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

    /**
     * @param  array<int, int|string>  $addressIds
     * @return Collection<int, IpAddress>
     */
    public function reserveAddresses(IpPool $pool, array $addressIds): Collection
    {
        $addressIds = array_values(array_unique(array_map(
            static fn (mixed $addressId): int => (int) $addressId,
            array_filter($addressIds, static fn (mixed $addressId): bool => $addressId !== null && $addressId !== ''),
        )));

        if ($addressIds === []) {
            throw new RuntimeException('Select at least one IP address to reserve.');
        }

        return DB::transaction(function () use ($pool, $addressIds): Collection {
            $addresses = $pool->addresses()
                ->whereIn('id', $addressIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($addresses->count() !== count($addressIds)) {
                throw new RuntimeException('One or more selected IPs do not belong to this pool.');
            }

            foreach ($addressIds as $addressId) {
                $address = $addresses->get($addressId);

                if (! in_array($address->status, [IpAddress::STATUS_AVAILABLE, IpAddress::STATUS_RELEASED], true)) {
                    throw new RuntimeException("IP {$address->address} is already in use and cannot be reserved.");
                }
            }

            $now = now();

            foreach ($addressIds as $addressId) {
                $address = $addresses->get($addressId);

                $address->forceFill([
                    'virtual_machine_id' => null,
                    'status' => IpAddress::STATUS_RESERVED,
                    'reserved_at' => $now,
                    'assigned_at' => null,
                    'released_at' => null,
                ])->save();
            }

            return $pool->addresses()
                ->whereIn('id', $addressIds)
                ->with(['virtualMachine.customer', 'virtualMachine.bundle'])
                ->orderBy('address')
                ->get();
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

    public function reserveSpecificForVm(IpAddress $address, VirtualMachine $vm): IpAddress
    {
        return DB::transaction(function () use ($address, $vm): IpAddress {
            $address = IpAddress::query()
                ->with('pool')
                ->lockForUpdate()
                ->findOrFail($address->id);

            $pool = $address->pool;

            if (! $pool->is_active) {
                throw new RuntimeException('The selected IP pool is not active.');
            }

            if ((int) $pool->proxmox_server_id !== (int) $vm->proxmox_server_id) {
                throw new RuntimeException('The selected IP pool belongs to a different Proxmox server.');
            }

            if (filled($pool->node) && filled($vm->node) && $pool->node !== $vm->node) {
                throw new RuntimeException('The selected IP pool belongs to a different Proxmox node.');
            }

            if ($address->virtual_machine_id && (int) $address->virtual_machine_id !== (int) $vm->id) {
                throw new RuntimeException("IP {$address->address} is already assigned to another VM.");
            }

            if (! in_array($address->status, [IpAddress::STATUS_AVAILABLE, IpAddress::STATUS_RELEASED, IpAddress::STATUS_RESERVED, IpAddress::STATUS_ASSIGNED], true)) {
                throw new RuntimeException("IP {$address->address} cannot be assigned in its current status.");
            }

            $previousAddress = $vm->reservedIpAddress()
                ->lockForUpdate()
                ->first();

            if ($previousAddress && (int) $previousAddress->id !== (int) $address->id) {
                $this->release($previousAddress);
            }

            $address->forceFill([
                'virtual_machine_id' => $vm->id,
                'status' => IpAddress::STATUS_ASSIGNED,
                'reserved_at' => $address->reserved_at ?: now(),
                'assigned_at' => now(),
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

    public function ensurePoolAddresses(IpPool $pool): void
    {
        foreach ($this->addressRange($pool->start_ip, $pool->end_ip) as $address) {
            $pool->addresses()->firstOrCreate(
                ['address' => $address],
                ['status' => IpAddress::STATUS_AVAILABLE],
            );
        }
    }
}

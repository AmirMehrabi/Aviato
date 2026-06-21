<?php

namespace App\Services;

use App\Models\IpAddress;
use App\Models\ProxmoxServer;
use App\Models\UsageAccrual;
use App\Models\VirtualMachine;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StaleVirtualMachineCleanupService
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
        private readonly IpPoolService $ipPools,
        private readonly UsageBillingService $usageBilling,
    ) {}

    /**
     * @return array{server: ProxmoxServer, remote_vmids: array<int, int>, stale: EloquentCollection<int, VirtualMachine>, checked_at: Carbon, error: string|null}
     */
    public function scanServer(ProxmoxServer $server, bool $includeDeleting = false): array
    {
        try {
            $remoteVmids = $this->proxmox->assignedGuestVmids($server);

            return [
                'server' => $server,
                'remote_vmids' => $remoteVmids,
                'stale' => $this->staleFromRemoteVmids($server, $remoteVmids, $includeDeleting),
                'checked_at' => now(),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'server' => $server,
                'remote_vmids' => [],
                'stale' => new EloquentCollection,
                'checked_at' => now(),
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return Collection<int, array{server: ProxmoxServer, remote_vmids: array<int, int>, stale: EloquentCollection<int, VirtualMachine>, checked_at: Carbon, error: string|null}>
     */
    public function scanAll(?int $serverId = null, bool $includeDeleting = false): Collection
    {
        return ProxmoxServer::query()
            ->when($serverId, fn ($query) => $query->whereKey($serverId))
            ->orderBy('name')
            ->get()
            ->map(fn (ProxmoxServer $server): array => $this->scanServer($server, $includeDeleting));
    }

    /**
     * @param  array<int, int|string>  $remoteVmids
     * @return EloquentCollection<int, VirtualMachine>
     */
    public function staleFromRemoteVmids(ProxmoxServer $server, array $remoteVmids, bool $includeDeleting = false): EloquentCollection
    {
        $remoteLookup = collect($remoteVmids)
            ->filter(fn (mixed $vmid): bool => is_numeric($vmid))
            ->map(fn (mixed $vmid): int => (int) $vmid)
            ->unique()
            ->flip();

        return $this->localCandidates($server, $includeDeleting)
            ->reject(fn (VirtualMachine $vm): bool => $remoteLookup->has((int) $vm->vmid))
            ->values();
    }

    /**
     * @return array{vm: VirtualMachine, usage_accrual: UsageAccrual|null, released_ip: string|null, deleted_vmid: int|null}
     */
    public function cleanup(VirtualMachine $vm, string $source = 'manual'): array
    {
        $vm->loadMissing(['proxmoxServer', 'reservedIpAddress', 'customer', 'bundle']);

        if (! $vm->proxmoxServer) {
            throw new RuntimeException('VM is not attached to a Proxmox server.');
        }

        if (! $vm->vmid) {
            throw new RuntimeException('VM does not have a VMID to verify against Proxmox.');
        }

        if (! $this->isStale($vm)) {
            throw new RuntimeException('VM still exists on Proxmox; local cleanup was blocked.');
        }

        return DB::transaction(function () use ($vm, $source): array {
            $locked = VirtualMachine::query()
                ->with(['reservedIpAddress', 'customer', 'bundle'])
                ->whereKey($vm->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isDeleted()) {
                return [
                    'vm' => $locked,
                    'usage_accrual' => null,
                    'released_ip' => null,
                    'deleted_vmid' => null,
                ];
            }

            $deletedVmid = $locked->vmid ? (int) $locked->vmid : null;
            $usageAccrual = $locked->customer ? $this->usageBilling->accrueVm($locked) : null;
            $releasedIp = $this->releaseVmIp($locked);

            $locked->forceFill([
                'status' => VirtualMachine::STATUS_DELETED,
                'vmid' => null,
                'deleted_at' => now(),
                'delete_requested_at' => $locked->delete_requested_at ?? now(),
                'delete_started_at' => $locked->delete_started_at ?? now(),
                'delete_failed_at' => null,
                'delete_error' => null,
                'delete_task_id' => null,
                'desired_state' => array_merge($locked->desired_state ?? [], ['status' => VirtualMachine::STATUS_DELETED]),
                'remote_state' => array_merge($locked->remote_state ?? [], [
                    'stale_cleanup' => [
                        'source' => $source,
                        'deleted_vmid' => $deletedVmid,
                        'released_ip' => $releasedIp,
                        'usage_accrual_id' => $usageAccrual?->id,
                        'cleaned_at' => now()->toISOString(),
                    ],
                ]),
            ])->save();

            return [
                'vm' => $locked,
                'usage_accrual' => $usageAccrual,
                'released_ip' => $releasedIp,
                'deleted_vmid' => $deletedVmid,
            ];
        });
    }

    public function isStale(VirtualMachine $vm): bool
    {
        if (! $vm->vmid || ! $vm->proxmoxServer) {
            return false;
        }

        return ! in_array((int) $vm->vmid, $this->proxmox->assignedGuestVmids($vm->proxmoxServer), true);
    }

    /**
     * @return EloquentCollection<int, VirtualMachine>
     */
    private function localCandidates(ProxmoxServer $server, bool $includeDeleting = false): EloquentCollection
    {
        return $server->virtualMachines()
            ->notDeleted()
            ->with(['customer', 'bundle', 'reservedIpAddress'])
            ->whereNotNull('vmid')
            ->when(
                $includeDeleting,
                fn ($query) => $query->where('status', '!=', VirtualMachine::STATUS_DELETED),
                fn ($query) => $query->whereNotIn('status', [VirtualMachine::STATUS_DELETING, VirtualMachine::STATUS_DELETED]),
            )
            ->orderBy('vmid')
            ->get();
    }

    private function releaseVmIp(VirtualMachine $vm): ?string
    {
        $address = $vm->reservedIpAddress;

        if (! $address || (int) $address->virtual_machine_id !== (int) $vm->id) {
            return null;
        }

        $releasedIp = $address->address;

        if (in_array($address->status, [IpAddress::STATUS_RESERVED, IpAddress::STATUS_ASSIGNED], true)) {
            $this->ipPools->release($address);
        }

        return $releasedIp;
    }
}

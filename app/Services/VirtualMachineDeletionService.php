<?php

namespace App\Services;

use App\Jobs\DeleteVirtualMachineJob;
use App\Models\IpAddress;
use App\Models\VirtualMachine;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class VirtualMachineDeletionService
{
    public function __construct(
        private readonly IpPoolService $ipPools,
        private readonly UsageBillingService $usageBilling,
    ) {}

    /**
     * @return array{status: string, queued: bool, finalized: bool, vm: VirtualMachine}
     */
    public function requestDelete(VirtualMachine $vm, string $actor = 'system'): array
    {
        $queued = false;
        $status = 'queued';

        $vm = DB::transaction(function () use ($vm, $actor, &$queued, &$status): VirtualMachine {
            $locked = VirtualMachine::query()
                ->with(['reservedIpAddress', 'customer', 'bundle'])
                ->whereKey($vm->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isDeleted()) {
                $status = 'already_deleted';

                return $locked;
            }

            if ($locked->isDeleting() && ! $locked->delete_failed_at && ! $locked->deleteAttemptIsStale()) {
                $status = 'already_queued';

                return $locked;
            }

            if ($locked->customer) {
                $this->usageBilling->chargeVm($locked);
            }

            $locked->forceFill([
                'status' => VirtualMachine::STATUS_DELETING,
                'delete_requested_at' => now(),
                'delete_started_at' => null,
                'delete_failed_at' => null,
                'delete_error' => null,
                'delete_task_id' => null,
                'desired_state' => array_merge($locked->desired_state ?? [], ['status' => VirtualMachine::STATUS_DELETING]),
                'remote_state' => array_merge($locked->remote_state ?? [], [
                    'delete_requested' => [
                        'actor' => $actor,
                        'requested_at' => now()->toISOString(),
                        'requeued_stale_delete' => $locked->deleteAttemptIsStale(),
                    ],
                ]),
            ])->save();

            $queued = true;

            return $locked;
        });

        if (! $queued) {
            return ['status' => $status, 'queued' => false, 'finalized' => $vm->isDeleted(), 'vm' => $vm];
        }

        if (! $vm->proxmox_server_id || ! $vm->node || ! $vm->vmid) {
            $result = $this->finalizeLocalDelete($vm, 'missing_proxmox_connection', [
                'actor' => $actor,
                'reason' => 'missing_proxmox_connection',
            ]);

            return ['status' => 'local_deleted', 'queued' => false, 'finalized' => true, 'vm' => $result['vm']];
        }

        DeleteVirtualMachineJob::dispatch($vm->id);

        return ['status' => 'queued', 'queued' => true, 'finalized' => false, 'vm' => $vm];
    }

    /**
     * @param  array<string, mixed>  $remoteEvidence
     * @return array{vm: VirtualMachine, wallet_transaction: WalletTransaction|null, released_ip: string|null, deleted_vmid: int|null}
     */
    public function finalizeLocalDelete(VirtualMachine $vm, string $source, array $remoteEvidence = []): array
    {
        return DB::transaction(function () use ($vm, $source, $remoteEvidence): array {
            $locked = VirtualMachine::query()
                ->with(['reservedIpAddress', 'customer', 'bundle'])
                ->whereKey($vm->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isDeleted()) {
                return [
                    'vm' => $locked,
                    'wallet_transaction' => null,
                    'released_ip' => null,
                    'deleted_vmid' => null,
                ];
            }

            $deletedVmid = $locked->vmid ? (int) $locked->vmid : null;
            $walletTransaction = $locked->customer ? $this->usageBilling->chargeVm($locked) : null;
            $releasedIp = $this->releaseVmIp($locked);
            $deleteSteps = data_get($locked->remote_state, 'delete_steps', []);
            $deleteSteps[] = array_merge($remoteEvidence, [
                'step' => 'local_finalize',
                'source' => $source,
                'at' => now()->toISOString(),
            ]);

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
                    'delete_steps' => $deleteSteps,
                    'deleted_vmid' => $deletedVmid,
                    'deleted_at' => now()->toISOString(),
                    'delete_finalized_by' => $source,
                    'released_ip' => $releasedIp,
                    'wallet_transaction_id' => $walletTransaction?->id,
                ]),
            ])->save();

            return [
                'vm' => $locked,
                'wallet_transaction' => $walletTransaction,
                'released_ip' => $releasedIp,
                'deleted_vmid' => $deletedVmid,
            ];
        });
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

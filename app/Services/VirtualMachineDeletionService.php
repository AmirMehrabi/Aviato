<?php

namespace App\Services;

use App\Jobs\DeleteVirtualMachineJob;
use App\Models\IpAddress;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        Log::info('Virtual machine delete requested', [
            'virtual_machine_id' => $vm->id,
            'uuid' => $vm->uuid,
            'actor' => $actor,
            'status' => $vm->status,
            'proxmox_server_id' => $vm->proxmox_server_id,
            'provider' => $vm->provider,
            'remote_id' => $vm->remote_id,
            'node' => $vm->node,
            'vmid' => $vm->vmid,
        ]);

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
                Log::info('Virtual machine delete request skipped because VM is already deleted', [
                    'virtual_machine_id' => $locked->id,
                    'actor' => $actor,
                ]);

                return $locked;
            }

            if ($locked->isDeleting() && ! $locked->delete_failed_at && ! $locked->deleteAttemptIsStale()) {
                $status = 'already_queued';
                Log::info('Virtual machine delete request skipped because delete is already active', [
                    'virtual_machine_id' => $locked->id,
                    'actor' => $actor,
                    'delete_requested_at' => $locked->delete_requested_at?->toISOString(),
                    'delete_started_at' => $locked->delete_started_at?->toISOString(),
                ]);

                return $locked;
            }

            if ($locked->customer) {
                $this->usageBilling->accrueVm($locked);
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
            Log::info('Virtual machine marked deleting and ready for delete execution', [
                'virtual_machine_id' => $locked->id,
                'actor' => $actor,
                'requeued_stale_delete' => $locked->deleteAttemptIsStale(),
                'proxmox_server_id' => $locked->proxmox_server_id,
                'node' => $locked->node,
                'vmid' => $locked->vmid,
            ]);

            return $locked;
        });

        if (! $queued) {
            return ['status' => $status, 'queued' => false, 'finalized' => $vm->isDeleted(), 'vm' => $vm];
        }

        if ($vm->isProxmox() && (! $vm->proxmox_server_id || ! $vm->node || ! $vm->vmid)) {
            Log::info('Virtual machine delete finalized locally because Proxmox connection data is incomplete', [
                'virtual_machine_id' => $vm->id,
                'actor' => $actor,
                'proxmox_server_id' => $vm->proxmox_server_id,
                'provider' => $vm->provider,
                'node' => $vm->node,
                'vmid' => $vm->vmid,
            ]);

            $result = $this->finalizeLocalDelete($vm, 'missing_proxmox_connection', [
                'actor' => $actor,
                'reason' => 'missing_proxmox_connection',
            ]);

            return ['status' => 'local_deleted', 'queued' => false, 'finalized' => true, 'vm' => $result['vm']];
        }

        Log::info('Virtual machine delete job dispatching', [
            'virtual_machine_id' => $vm->id,
            'actor' => $actor,
            'proxmox_server_id' => $vm->proxmox_server_id,
            'node' => $vm->node,
            'vmid' => $vm->vmid,
        ]);

        DeleteVirtualMachineJob::dispatch($vm->id)->onQueue(DeleteVirtualMachineJob::QUEUE);

        return ['status' => 'queued', 'queued' => true, 'finalized' => false, 'vm' => $vm];
    }

    /**
     * @param  array<string, mixed>  $remoteEvidence
     * @return array{vm: VirtualMachine, usage_accrual: \App\Models\UsageAccrual|null, released_ip: string|null, deleted_vmid: int|null}
     */
    public function finalizeLocalDelete(VirtualMachine $vm, string $source, array $remoteEvidence = []): array
    {
        Log::info('Virtual machine local delete finalization starting', [
            'virtual_machine_id' => $vm->id,
            'source' => $source,
            'remote_evidence' => $remoteEvidence,
        ]);

        return DB::transaction(function () use ($vm, $source, $remoteEvidence): array {
            $locked = VirtualMachine::query()
                ->with(['reservedIpAddress', 'customer', 'bundle'])
                ->whereKey($vm->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isDeleted()) {
                Log::info('Virtual machine local delete finalization skipped because VM is already deleted', [
                    'virtual_machine_id' => $locked->id,
                    'source' => $source,
                ]);

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
                    'usage_accrual_id' => $usageAccrual?->id,
                ]),
            ])->save();

            Log::info('Virtual machine local delete finalization completed', [
                'virtual_machine_id' => $locked->id,
                'source' => $source,
                'deleted_vmid' => $deletedVmid,
                'released_ip' => $releasedIp,
                'usage_accrual_id' => $usageAccrual?->id,
            ]);

            return [
                'vm' => $locked,
                'usage_accrual' => $usageAccrual,
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

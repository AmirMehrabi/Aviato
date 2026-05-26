<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteVirtualMachineJob implements ShouldBeUnique, ShouldQueue
{
    use FoundationQueueable;

    public int $timeout = 900;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $virtualMachineId) {}

    public function uniqueId(): string
    {
        return 'delete-vm-'.$this->virtualMachineId;
    }

    public function handle(ProxmoxService $proxmox, IpPoolService $ipPools): void
    {
        $vm = VirtualMachine::query()
            ->with(['proxmoxServer', 'reservedIpAddress'])
            ->findOrFail($this->virtualMachineId);

        if ($vm->isDeleted()) {
            return;
        }

        $history = data_get($vm->remote_state, 'delete_steps', []);

        try {
            if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
                throw new \RuntimeException('VM is missing Proxmox server, node, or VMID.');
            }

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_DELETING,
                'delete_started_at' => $vm->delete_started_at ?? now(),
                'delete_failed_at' => null,
                'delete_error' => null,
            ])->save();

            $server = $vm->proxmoxServer;
            $node = $vm->node;
            $vmid = (int) $vm->vmid;
            $remoteStatus = $proxmox->vmStatus($server, $node, $vmid);
            $history[] = ['step' => 'status', 'result' => $remoteStatus, 'at' => now()->toISOString()];
            $this->recordHistory($vm, $history);

            if ($remoteStatus !== null) {
                if (($remoteStatus['status'] ?? null) === 'running') {
                    try {
                        $shutdown = $proxmox->shutdownVm($server, $node, $vmid, false);
                        $history[] = ['step' => 'shutdown', 'result' => $shutdown, 'at' => now()->toISOString()];
                        $vm->forceFill(['delete_task_id' => $shutdown['task_id'] ?? null])->save();
                        $proxmox->waitForTask($server, $node, (string) $shutdown['task_id'], 180);
                        $history[] = ['step' => 'shutdown_wait', 'result' => 'OK', 'at' => now()->toISOString()];

                        $afterShutdown = $proxmox->vmStatus($server, $node, $vmid);
                        $history[] = ['step' => 'status_after_shutdown', 'result' => $afterShutdown, 'at' => now()->toISOString()];

                        if (($afterShutdown['status'] ?? null) === 'running') {
                            throw new \RuntimeException('VM is still running after graceful shutdown.');
                        }
                    } catch (Throwable $shutdownException) {
                        $history[] = ['step' => 'shutdown_failed', 'error' => $shutdownException->getMessage(), 'at' => now()->toISOString()];
                        $stop = $proxmox->stopVm($server, $node, $vmid);
                        $history[] = ['step' => 'force_stop', 'result' => $stop, 'at' => now()->toISOString()];
                        $vm->forceFill(['delete_task_id' => $stop['task_id'] ?? null])->save();
                        $proxmox->waitForTask($server, $node, (string) $stop['task_id'], 180);
                        $history[] = ['step' => 'force_stop_wait', 'result' => 'OK', 'at' => now()->toISOString()];
                    }

                    $this->recordHistory($vm, $history);
                }

                $delete = $proxmox->deleteVm($server, $node, $vmid, true);
                $history[] = ['step' => 'delete', 'result' => $delete, 'at' => now()->toISOString()];
                $vm->forceFill(['delete_task_id' => $delete['task_id'] ?? null])->save();
                $proxmox->waitForTask($server, $node, (string) $delete['task_id'], 300);
                $history[] = ['step' => 'delete_wait', 'result' => 'OK', 'at' => now()->toISOString()];
            } else {
                $history[] = ['step' => 'remote_missing', 'result' => 'already_deleted', 'at' => now()->toISOString()];
            }

            DB::transaction(function () use ($vm, $ipPools, $history): void {
                $vm->refresh()->loadMissing('reservedIpAddress');
                $address = $vm->reservedIpAddress;

                if ($address && (int) $address->virtual_machine_id === (int) $vm->id) {
                    $ipPools->release($address);
                }

                $vm->forceFill([
                    'status' => VirtualMachine::STATUS_DELETED,
                    'vmid' => null,
                    'deleted_at' => now(),
                    'delete_failed_at' => null,
                    'delete_error' => null,
                    'delete_task_id' => null,
                    'desired_state' => array_merge($vm->desired_state ?? [], ['status' => VirtualMachine::STATUS_DELETED]),
                    'remote_state' => array_merge($vm->remote_state ?? [], [
                        'delete_steps' => $history,
                        'deleted_vmid' => $vm->vmid,
                        'deleted_at' => now()->toISOString(),
                    ]),
                ])->save();
            });
        } catch (Throwable $exception) {
            Log::error('Cloud VM deletion failed', [
                'virtual_machine_id' => $vm->id,
                'vmid' => $vm->vmid,
                'message' => $exception->getMessage(),
            ]);

            $history[] = ['step' => 'failed', 'error' => $exception->getMessage(), 'at' => now()->toISOString()];

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_DELETING,
                'delete_failed_at' => now(),
                'delete_error' => $exception->getMessage(),
                'remote_state' => array_merge($vm->remote_state ?? [], ['delete_steps' => $history]),
            ])->save();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     */
    private function recordHistory(VirtualMachine $vm, array $history): void
    {
        $vm->forceFill([
            'remote_state' => array_merge($vm->remote_state ?? [], ['delete_steps' => $history]),
        ])->save();
    }
}

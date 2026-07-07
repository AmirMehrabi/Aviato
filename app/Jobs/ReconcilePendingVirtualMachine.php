<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePendingVirtualMachine implements ShouldQueue
{
    use FoundationQueueable;

    public const QUEUE = 'provisioning';

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly int $virtualMachineId,
    ) {}

    public function handle(ProxmoxService $proxmox, IpPoolService $ipPools): void
    {
        $vm = VirtualMachine::query()
            ->with(['proxmoxServer', 'reservedIpAddress'])
            ->find($this->virtualMachineId);

        if (! $vm
            || $vm->provisioning_status !== VirtualMachine::PROVISION_PENDING
            || ! $vm->isProxmox()) {
            return;
        }

        if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
            $this->record($vm, [
                'result' => 'skipped_missing_remote_identity',
            ]);

            return;
        }

        try {
            $config = $proxmox->vmConfigOrNull($vm->proxmoxServer, $vm->node, (int) $vm->vmid);

            if (($config['name'] ?? null) !== $vm->name) {
                $this->record($vm, [
                    'result' => $config === null ? 'remote_vm_missing' : 'remote_name_mismatch',
                    'remote_name' => $config['name'] ?? null,
                ]);

                return;
            }

            $status = $proxmox->vmStatus($vm->proxmoxServer, $vm->node, (int) $vm->vmid);

            if ($vm->reservedIpAddress) {
                $ipPools->assign($vm->reservedIpAddress, $vm);
            }

            $isRunning = ($status['status'] ?? null) === 'running';

            $vm->forceFill([
                'status' => $isRunning ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'last_seen_at' => now(),
                'last_started_at' => $isRunning ? ($vm->last_started_at ?: now()) : $vm->last_started_at,
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'reconciled_at' => now()->toISOString(),
                    'reconcile_result' => 'matched_ready',
                    'reconcile_status' => $status,
                ]),
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Pending VM reconciliation failed', [
                'virtual_machine_id' => $vm->id,
                'vmid' => $vm->vmid,
                'message' => $exception->getMessage(),
            ]);

            $this->record($vm, [
                'result' => 'error',
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function record(VirtualMachine $vm, array $state): void
    {
        $vm->forceFill([
            'remote_state' => array_merge($vm->remote_state ?? [], [
                'last_reconcile_at' => now()->toISOString(),
                'last_reconcile' => $state,
            ]),
        ])->save();
    }
}

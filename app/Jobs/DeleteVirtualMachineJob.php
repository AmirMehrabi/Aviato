<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use App\Services\VirtualMachineDeletionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeleteVirtualMachineJob implements ShouldBeUnique, ShouldQueue
{
    use FoundationQueueable;

    public int $timeout = 900;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 90, 180, 300, 600];

    public int $uniqueFor = 900;

    public function __construct(public readonly int $virtualMachineId) {}

    public function uniqueId(): string
    {
        return 'delete-vm-'.$this->virtualMachineId;
    }

    public function handle(ProxmoxService $proxmox, VirtualMachineDeletionService $deletions): void
    {
        $vm = VirtualMachine::query()
            ->with(['proxmoxServer', 'reservedIpAddress'])
            ->findOrFail($this->virtualMachineId);

        Log::info('Cloud VM deletion job started', [
            'virtual_machine_id' => $vm->id,
            'status' => $vm->status,
            'proxmox_server_id' => $vm->proxmox_server_id,
            'node' => $vm->node,
            'vmid' => $vm->vmid,
            'attempt' => $this->attempts(),
            'has_queue_job' => $this->job !== null,
        ]);

        if ($vm->isDeleted()) {
            Log::info('Cloud VM deletion job skipped because VM is already deleted', [
                'virtual_machine_id' => $vm->id,
            ]);

            return;
        }

        $history = data_get($vm->remote_state, 'delete_steps', []);

        try {
            if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
                Log::info('Cloud VM deletion job cannot contact Proxmox because VM connection data is incomplete', [
                    'virtual_machine_id' => $vm->id,
                    'has_proxmox_server' => (bool) $vm->proxmoxServer,
                    'node' => $vm->node,
                    'vmid' => $vm->vmid,
                ]);

                throw new RuntimeException('VM is missing Proxmox server, node, or VMID.');
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

            $config = $proxmox->vmConfigOrNull($server, $node, $vmid);
            $history[] = ['step' => 'config', 'result' => $config, 'at' => now()->toISOString()];

            if ($config === null) {
                Log::info('Cloud VM deletion found no matching Proxmox config; finalizing locally', [
                    'virtual_machine_id' => $vm->id,
                    'proxmox_server_id' => $server->id,
                    'node' => $node,
                    'vmid' => $vmid,
                ]);

                $this->recordHistory($vm, $history);
                $deletions->finalizeLocalDelete($vm, 'remote_config_missing', [
                    'step' => 'remote_config_missing',
                    'node' => $node,
                    'vmid' => $vmid,
                ]);

                return;
            }

            if (! $this->remoteVmMatchesPanelVm($vm, $config)) {
                Log::warning('Cloud VM deletion blocked remote delete because Proxmox VM identity mismatched; finalizing locally', [
                    'virtual_machine_id' => $vm->id,
                    'proxmox_server_id' => $server->id,
                    'node' => $node,
                    'vmid' => $vmid,
                    'expected_name' => $vm->name,
                    'remote_name' => $config['name'] ?? null,
                ]);

                $this->recordHistory($vm, $history);
                $deletions->finalizeLocalDelete($vm, 'remote_identity_mismatch', [
                    'step' => 'remote_identity_mismatch',
                    'node' => $node,
                    'vmid' => $vmid,
                    'expected_name' => $vm->name,
                    'remote_name' => $config['name'] ?? null,
                ]);

                return;
            }

            $this->recordHistory($vm, $history);

            $remoteStatus = $proxmox->vmStatus($server, $node, $vmid);
            $history[] = ['step' => 'status', 'result' => $remoteStatus, 'at' => now()->toISOString()];
            $this->recordHistory($vm, $history);
            $remoteMissing = $remoteStatus === null;

            if (! $remoteMissing) {
                if (($remoteStatus['status'] ?? null) === 'running') {
                    try {
                        Log::info('Cloud VM deletion requesting Proxmox shutdown', [
                            'virtual_machine_id' => $vm->id,
                            'proxmox_server_id' => $server->id,
                            'node' => $node,
                            'vmid' => $vmid,
                        ]);

                        $shutdown = $proxmox->shutdownVm($server, $node, $vmid, false);
                        $history[] = ['step' => 'shutdown', 'result' => $shutdown, 'at' => now()->toISOString()];
                        $vm->forceFill(['delete_task_id' => $shutdown['task_id'] ?? null])->save();
                        $proxmox->waitForTask($server, $node, (string) $shutdown['task_id'], 180);
                        $history[] = ['step' => 'shutdown_wait', 'result' => 'OK', 'at' => now()->toISOString()];

                        $afterShutdown = $proxmox->waitForVmStopped($server, $node, $vmid, 60);
                        $history[] = ['step' => 'status_after_shutdown', 'result' => $afterShutdown, 'at' => now()->toISOString()];

                        if ($afterShutdown === null) {
                            $remoteMissing = true;
                            $history[] = ['step' => 'remote_missing_after_shutdown', 'result' => 'already_deleted', 'at' => now()->toISOString()];
                        } elseif (($afterShutdown['status'] ?? null) === 'running') {
                            throw new RuntimeException('VM is still running after graceful shutdown.');
                        }
                    } catch (Throwable $shutdownException) {
                        if ($this->isRemoteMissingException($shutdownException)) {
                            $remoteMissing = true;
                            $history[] = ['step' => 'remote_missing_during_shutdown', 'error' => $shutdownException->getMessage(), 'at' => now()->toISOString()];
                            $this->recordHistory($vm, $history);
                        } else {
                            $history[] = ['step' => 'shutdown_failed', 'error' => $shutdownException->getMessage(), 'at' => now()->toISOString()];

                            try {
                                Log::info('Cloud VM deletion requesting Proxmox force stop', [
                                    'virtual_machine_id' => $vm->id,
                                    'proxmox_server_id' => $server->id,
                                    'node' => $node,
                                    'vmid' => $vmid,
                                ]);

                                $stop = $proxmox->stopVm($server, $node, $vmid);
                                $history[] = ['step' => 'force_stop', 'result' => $stop, 'at' => now()->toISOString()];
                                $vm->forceFill(['delete_task_id' => $stop['task_id'] ?? null])->save();
                                $proxmox->waitForTask($server, $node, (string) $stop['task_id'], 180);
                                $history[] = ['step' => 'force_stop_wait', 'result' => 'OK', 'at' => now()->toISOString()];
                            } catch (Throwable $stopException) {
                                if (! $this->isRemoteMissingException($stopException)) {
                                    throw $stopException;
                                }

                                $remoteMissing = true;
                                $history[] = ['step' => 'remote_missing_during_force_stop', 'error' => $stopException->getMessage(), 'at' => now()->toISOString()];
                            }
                        }
                    }

                    $this->recordHistory($vm, $history);
                }

                if (! $remoteMissing) {
                    try {
                        Log::info('Cloud VM deletion requesting Proxmox delete', [
                            'virtual_machine_id' => $vm->id,
                            'proxmox_server_id' => $server->id,
                            'node' => $node,
                            'vmid' => $vmid,
                        ]);

                        $delete = $proxmox->deleteVm($server, $node, $vmid, true);
                        $history[] = ['step' => 'delete', 'result' => $delete, 'at' => now()->toISOString()];
                        $vm->forceFill(['delete_task_id' => $delete['task_id'] ?? null])->save();
                        $proxmox->waitForTask($server, $node, (string) $delete['task_id'], 300);
                        $history[] = ['step' => 'delete_wait', 'result' => 'OK', 'at' => now()->toISOString()];
                    } catch (Throwable $deleteException) {
                        if (! $this->isRemoteMissingException($deleteException)) {
                            throw $deleteException;
                        }

                        $remoteMissing = true;
                        $history[] = ['step' => 'remote_missing_during_delete', 'error' => $deleteException->getMessage(), 'at' => now()->toISOString()];
                    }
                }
            } else {
                $history[] = ['step' => 'remote_missing', 'result' => 'already_deleted', 'at' => now()->toISOString()];
            }

            $this->recordHistory($vm, $history);
            $deletions->finalizeLocalDelete($vm, 'remote_deleted', [
                'step' => 'remote_deleted',
                'node' => $node,
                'vmid' => $vmid,
            ]);
        } catch (Throwable $exception) {
            $hasAttemptsRemaining = $this->hasAttemptsRemaining();

            Log::error('Cloud VM deletion failed', [
                'virtual_machine_id' => $vm->id,
                'vmid' => $vm->vmid,
                'attempt' => $this->attempts(),
                'will_retry' => $hasAttemptsRemaining,
                'message' => $exception->getMessage(),
            ]);

            $history[] = ['step' => 'failed', 'error' => $exception->getMessage(), 'at' => now()->toISOString()];

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_DELETING,
                'delete_failed_at' => now(),
                'delete_error' => $exception->getMessage(),
                'remote_state' => array_merge($vm->remote_state ?? [], ['delete_steps' => $history]),
            ])->save();

            if ($hasAttemptsRemaining) {
                throw $exception;
            }
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

    private function isRemoteMissingException(Throwable $exception): bool
    {
        if ($exception instanceof RequestException && $exception->response->status() === 404) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'does not exist')
            || str_contains($message, 'not found')
            || str_contains($message, 'no such vm')
            || str_contains($message, 'unable to find vmid')
            || str_contains($message, 'configuration file')
            || str_contains($message, 'already deleted');
    }

    private function hasAttemptsRemaining(): bool
    {
        return $this->job !== null && $this->attempts() < $this->tries;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function remoteVmMatchesPanelVm(VirtualMachine $vm, array $config): bool
    {
        return ($config['name'] ?? null) === $vm->name;
    }
}

<?php

namespace App\Jobs;

use App\Exceptions\ProxmoxDeletionPreflightException;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Notifications\VmDeletionBlockedNotification;
use App\Services\HetznerCloudService;
use App\Services\ProxmoxService;
use App\Services\UsageBillingService;
use App\Services\VirtualMachineDeletionService;
use App\Services\VmActivityRecorder;
use App\Services\VmWalletRetentionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class DeleteVirtualMachineJob implements ShouldBeUnique, ShouldQueue
{
    use FoundationQueueable;

    public const QUEUE = 'deletions';

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

    public function handle(ProxmoxService $proxmox, VirtualMachineDeletionService $deletions, ?HetznerCloudService $hetzner = null): void
    {
        $vm = VirtualMachine::query()
            ->with(['proxmoxServer', 'reservedIpAddress', 'infrastructureLocation.hetznerAccount'])
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

        if (! $vm->isDeleting()) {
            Log::warning('Cloud VM deletion job skipped because delete intent is no longer active', [
                'virtual_machine_id' => $vm->id,
                'status' => $vm->status,
                'delete_requested_at' => $vm->delete_requested_at?->toISOString(),
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        if (! app(VmWalletRetentionService::class)->allowDeletion($vm)) {
            return;
        }

        if ($this->isWalletDeletion($vm)) {
            try {
                $targets = $this->checkProxmoxDeletionTargets($vm, $proxmox);
                $started = DB::transaction(function () use ($vm, $targets): bool {
                    $locked = VirtualMachine::query()->lockForUpdate()->findOrFail($vm->id);
                    if (! $locked->isDeleting() || ! $this->isWalletDeletion($locked)
                        || ! app(VmWalletRetentionService::class)->allowDeletion($locked)) {
                        return false;
                    }

                    $locked->forceFill([
                        'node' => $targets['vm_node'],
                        'delete_started_at' => $locked->delete_started_at ?? now(),
                        'remote_state' => array_merge($locked->remote_state ?? [], [
                            'wallet_delete_started_at' => data_get($locked->remote_state, 'wallet_delete_started_at', now()->toISOString()),
                        ]),
                    ])->save();

                    return true;
                });
                if (! $started) {
                    return;
                }
                $vm->refresh();
                $this->deleteExpiredBackups($vm, $proxmox, $targets['backups']);
            } catch (ProxmoxDeletionPreflightException $exception) {
                $this->blockWalletDeletion($vm, $exception);

                return;
            } catch (Throwable $exception) {
                if ($this->hasAttemptsRemaining()) {
                    $vm->forceFill(['delete_error' => $exception->getMessage()])->save();

                    throw $exception;
                }

                $this->blockWalletDeletion($vm, $exception);

                return;
            }
            if ($vm->isProxmox() && (! $vm->proxmox_server_id || ! $vm->node || ! $vm->vmid)) {
                $deletions->finalizeLocalDelete($vm, 'wallet_expired_missing_connection');

                return;
            }
        }

        if ($vm->isHetzner()) {
            $this->handleHetzner($vm, $deletions, $hetzner ?? app(HetznerCloudService::class));

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

                        $shutdown = $proxmox->shutdownVm($server, $node, $vmid, false, [
                            'source' => 'delete_job',
                            'virtual_machine_id' => $vm->id,
                            'attempt' => $this->attempts(),
                        ]);
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
            if ($this->isWalletDeletion($vm) && ! $hasAttemptsRemaining) {
                $this->blockWalletDeletion($vm, $exception);

                return;
            }

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
                'delete_failed_at' => $this->isWalletDeletion($vm) ? null : now(),
                'delete_error' => $exception->getMessage(),
                'remote_state' => array_merge($vm->remote_state ?? [], ['delete_steps' => $history]),
            ])->save();
            if (! $hasAttemptsRemaining) {
                app(VmActivityRecorder::class)->record($vm, 'delete', 'failed', 'حذف سرور ناموفق بود', 'لطفاً با پشتیبانی تماس بگیرید یا دوباره تلاش کنید.');
            }

            if ($hasAttemptsRemaining) {
                throw $exception;
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $vm = VirtualMachine::query()->find($this->virtualMachineId);
        if ($vm && $vm->isDeleting() && $this->isWalletDeletion($vm)) {
            $this->blockWalletDeletion($vm, $exception);
        }
    }

    private function isWalletDeletion(VirtualMachine $vm): bool
    {
        return data_get($vm->remote_state, 'delete_requested.actor') === VmWalletRetentionService::DELETE_ACTOR;
    }

    private function blockWalletDeletion(VirtualMachine $vm, Throwable $exception): void
    {
        $blocked = VirtualMachine::query()->whereKey($vm->id)->where('status', VirtualMachine::STATUS_DELETING)
            ->whereNull('delete_failed_at')
            ->update(['delete_failed_at' => now(), 'delete_error' => $exception->getMessage()]);
        if (! $blocked) {
            return;
        }
        $vm->refresh();
        Log::error('Automatic VM deletion blocked', [
            'virtual_machine_id' => $vm->id,
            'error' => $vm->delete_error,
        ]);
        $admins = User::query()->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->allows('virtual-machines.delete'));
        Notification::send($admins, new VmDeletionBlockedNotification($vm));
    }

    /** @return array{vm_node: ?string, backups: array<int, string>} */
    private function checkProxmoxDeletionTargets(VirtualMachine $vm, ProxmoxService $proxmox): array
    {
        $targets = ['vm_node' => $vm->node, 'backups' => []];
        if (! $vm->isProxmox()) {
            return $targets;
        }

        $backups = $vm->backups()->where('status', '!=', VmBackup::STATUS_DELETED)->whereNotNull('volid')->get();
        if (! $vm->vmid && $backups->isEmpty()) {
            return $targets;
        }
        $server = $vm->proxmoxServer;
        if (! $server) {
            throw new ProxmoxDeletionPreflightException('The Proxmox server is missing for this VM or its backups.');
        }

        $nodes = collect($proxmox->deletionNodes($server))->keyBy('node');
        if ($nodes->isEmpty()) {
            throw new RuntimeException('The Proxmox API returned no visible nodes.');
        }
        $aliases = config('proxmox.deletion_node_aliases', [])[$server->id] ?? [];
        $resolveNode = function (?string $node) use ($nodes, $aliases): string {
            $target = $nodes->has($node) ? $node : ($aliases[$node] ?? null);
            if ($target === null || ! $nodes->has($target)) {
                throw new ProxmoxDeletionPreflightException(sprintf(
                    'Proxmox node "%s" is missing from live inventory. Check the recorded node or configure a verified node alias.',
                    $node ?? '(unset)',
                ));
            }
            if (data_get($nodes->get($target), 'status') === 'offline') {
                throw new RuntimeException('Proxmox node "'.$target.'" is offline.');
            }

            return $target;
        };

        if ($vm->vmid) {
            $targets['vm_node'] = $resolveNode($vm->node);
        }
        $storageByNode = [];
        foreach ($backups as $backup) {
            $node = $resolveNode($backup->node);
            $storageByNode[$node] ??= collect($proxmox->deletionBackupStorage($server, $node))->keyBy('storage');
            $storage = $storageByNode[$node]->get($backup->storage);
            if (! $storage || (isset($storage['active']) && ! $storage['active'])) {
                throw new ProxmoxDeletionPreflightException(sprintf(
                    'Backup #%d storage "%s" is missing or inactive on Proxmox node "%s". Verify the backup location and storage configuration.',
                    $backup->id,
                    $backup->storage ?? '(unset)',
                    $node,
                ));
            }
            $targets['backups'][$backup->id] = $node;
        }

        return $targets;
    }

    /** @param array<int, string> $backupNodes */
    private function deleteExpiredBackups(VirtualMachine $vm, ProxmoxService $proxmox, array $backupNodes): void
    {
        $vm->backupPolicy()->update(['is_enabled' => false, 'next_run_at' => null]);
        $vm->backups()->where('status', VmBackup::STATUS_QUEUED)->update([
            'status' => VmBackup::STATUS_DELETED, 'deleted_at' => now(),
        ]);
        if ($vm->backups()->where('status', VmBackup::STATUS_RUNNING)->exists()) {
            throw new RuntimeException('An active backup must finish before expired VM deletion.');
        }
        foreach ($vm->backups()->where('status', '!=', VmBackup::STATUS_DELETED)
            ->where(fn ($query) => $query->where('status', VmBackup::STATUS_READY)->orWhereNotNull('volid'))
            ->get() as $backup) {
            app(UsageBillingService::class)->accrueBackup($backup);
            if ($backup->volid) {
                $node = $backupNodes[$backup->id] ?? null;
                if (! $vm->proxmoxServer || ! $node || ! $backup->storage) {
                    throw new RuntimeException('Backup storage connection is missing; expired backup deletion will be retried.');
                }
                try {
                    $proxmox->deleteBackupFile($vm->proxmoxServer, $node, $backup->storage, $backup->volid);
                } catch (Throwable $exception) {
                    if (! $this->isRemoteMissingException($exception)
                        || str_contains(strtolower($exception->getMessage()), 'storage ')) {
                        throw $exception;
                    }
                }
            }
            $backup->forceFill(['status' => VmBackup::STATUS_DELETED, 'deleted_at' => now()])->save();
        }
    }

    private function handleHetzner(VirtualMachine $vm, VirtualMachineDeletionService $deletions, HetznerCloudService $hetzner): void
    {
        $history = data_get($vm->remote_state, 'delete_steps', []);
        $account = $vm->infrastructureLocation?->hetznerAccount;

        try {
            if (! $account || ! $vm->remote_id) {
                throw new RuntimeException('VM is missing Hetzner account or remote server ID.');
            }

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_DELETING,
                'delete_started_at' => $vm->delete_started_at ?? now(),
                'delete_failed_at' => null,
                'delete_error' => null,
            ])->save();

            $server = $hetzner->server($account, $vm->remote_id);
            $history[] = ['step' => 'status', 'result' => $server, 'at' => now()->toISOString()];
            $this->recordHistory($vm, $history);

            if ($server === null) {
                $history[] = ['step' => 'remote_missing', 'result' => 'already_deleted', 'at' => now()->toISOString()];
            } else {
                if (($server['status'] ?? null) === 'running') {
                    try {
                        $shutdown = $hetzner->shutdown($account, $vm->remote_id);
                        $history[] = ['step' => 'shutdown', 'result' => $shutdown, 'at' => now()->toISOString()];
                        $vm->forceFill(['delete_task_id' => $shutdown['action']['id'] ?? null])->save();
                        $hetzner->waitForAction($account, $shutdown['action']['id'] ?? null, 180);
                    } catch (Throwable $shutdownException) {
                        $history[] = ['step' => 'shutdown_failed', 'error' => $shutdownException->getMessage(), 'at' => now()->toISOString()];
                        $powerOff = $hetzner->powerOff($account, $vm->remote_id);
                        $history[] = ['step' => 'poweroff', 'result' => $powerOff, 'at' => now()->toISOString()];
                        $vm->forceFill(['delete_task_id' => $powerOff['action']['id'] ?? null])->save();
                        $hetzner->waitForAction($account, $powerOff['action']['id'] ?? null, 180);
                    }
                }

                $delete = $hetzner->deleteServer($account, $vm->remote_id);
                $history[] = ['step' => 'delete', 'result' => $delete, 'at' => now()->toISOString()];
            }

            $this->recordHistory($vm, $history);
            $deletions->finalizeLocalDelete($vm, 'hetzner_remote_deleted', [
                'step' => 'remote_deleted',
                'remote_id' => $vm->remote_id,
            ]);
        } catch (Throwable $exception) {
            $hasAttemptsRemaining = $this->hasAttemptsRemaining();

            if ($this->isWalletDeletion($vm) && ! $hasAttemptsRemaining) {
                $this->blockWalletDeletion($vm, $exception);

                return;
            }

            Log::error('Hetzner VM deletion failed', [
                'virtual_machine_id' => $vm->id,
                'remote_id' => $vm->remote_id,
                'attempt' => $this->attempts(),
                'will_retry' => $hasAttemptsRemaining,
                'message' => $exception->getMessage(),
            ]);

            $vm->forceFill([
                'delete_failed_at' => $hasAttemptsRemaining ? null : now(),
                'delete_error' => $exception->getMessage(),
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'delete_steps' => $history,
                    $hasAttemptsRemaining ? 'delete_retrying_at' : 'delete_failed_at' => now()->toISOString(),
                ]),
            ])->save();
            if (! $hasAttemptsRemaining) {
                app(VmActivityRecorder::class)->record($vm, 'delete', 'failed', 'حذف سرور ناموفق بود', 'لطفاً با پشتیبانی تماس بگیرید یا دوباره تلاش کنید.');
            }

            throw $exception;
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

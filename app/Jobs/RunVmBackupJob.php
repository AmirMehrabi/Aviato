<?php

namespace App\Jobs;

use App\Models\VmBackup;
use App\Services\ProxmoxService;
use App\Services\VmBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunVmBackupJob implements ShouldQueue
{
    use Queueable;

    public const QUEUE = 'backups';

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly int $backupId) {}

    public function handle(ProxmoxService $proxmox, VmBackupService $backups): void
    {
        $backup = VmBackup::query()
            ->with(['virtualMachine.proxmoxServer', 'virtualMachine.backupPolicy'])
            ->findOrFail($this->backupId);
        $vm = $backup->virtualMachine;
        $server = $vm->proxmoxServer;
        $policy = $vm->backupPolicy;

        if (! $server) {
            throw new \RuntimeException('Backup cannot run without a Proxmox server.');
        }

        try {
            $backup->forceFill([
                'status' => VmBackup::STATUS_RUNNING,
                'started_at' => now(),
                'node' => $vm->node,
            ])->save();

            $storage = $policy?->backup_storage ?: null;
            if (! $storage) {
                $storage = $proxmox->backupStorages($server, $vm->node)[0]['storage'] ?? null;
            }

            $result = $proxmox->startBackup($server, [
                'node' => $vm->node,
                'vmid' => $vm->vmid,
                'storage' => $storage,
                'mode' => $policy?->mode ?: 'snapshot',
                'compress' => $policy?->compression ?: 'zstd',
            ]);

            $backup->forceFill([
                'proxmox_task_id' => $result['task_id'] ?? null,
                'storage' => $storage,
                'remote_state' => ['start' => $result],
            ])->save();

            if (! empty($result['task_id'])) {
                $wait = $proxmox->waitForTask($server, $vm->node, $result['task_id'], 1800);
                $backup->forceFill(['remote_state' => ['start' => $result, 'wait' => $wait]])->save();
            }

            $file = $proxmox->backupFilesForVm($server, $vm->node, $vm->vmid, $storage)[0] ?? [];

            $backup->forceFill([
                'status' => VmBackup::STATUS_READY,
                'storage' => $file['storage'] ?? $storage,
                'volid' => $file['volid'] ?? null,
                'filename' => $file['filename'] ?? null,
                'size_bytes' => (int) ($file['size'] ?? 0),
                'finished_at' => now(),
                'last_billed_at' => now(),
                'error' => null,
                'remote_state' => array_merge($backup->remote_state ?? [], ['file' => $file]),
            ])->save();

            $backups->enforceRetention($vm->refresh(), $proxmox);
        } catch (\Throwable $exception) {
            $backup->forceFill([
                'status' => VmBackup::STATUS_FAILED,
                'finished_at' => now(),
                'error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}

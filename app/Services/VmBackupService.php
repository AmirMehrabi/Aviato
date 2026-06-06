<?php

namespace App\Services;

use App\Jobs\RunVmBackupJob;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmBackupPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class VmBackupService
{
    public function queueManualBackup(VirtualMachine $vm): VmBackup
    {
        $this->assertBackupable($vm);
        $this->assertNoRunningBackup($vm);

        $backup = VmBackup::create([
            'virtual_machine_id' => $vm->id,
            'source' => VmBackup::SOURCE_MANUAL,
            'status' => VmBackup::STATUS_QUEUED,
            'node' => $vm->node,
        ]);

        RunVmBackupJob::dispatch($backup->id)->onQueue(RunVmBackupJob::QUEUE);

        return $backup;
    }

    public function updatePolicy(VirtualMachine $vm, array $data): VmBackupPolicy
    {
        $policy = $vm->backupPolicy()->firstOrNew(['virtual_machine_id' => $vm->id]);
        $policy->fill([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'frequency' => $data['frequency'] ?? VmBackupPolicy::FREQUENCY_DAILY,
            'preferred_time' => $data['preferred_time'] ?? '02:00',
            'retention_count' => (int) ($data['retention_count'] ?? 3),
            'backup_storage' => $data['backup_storage'] ?? $policy->backup_storage,
            'mode' => 'snapshot',
            'compression' => 'zstd',
        ]);
        $policy->save();

        if ($policy->is_enabled) {
            $policy->scheduleNext();
        } else {
            $policy->forceFill(['next_run_at' => null])->save();
        }

        return $policy;
    }

    /**
     * @return Collection<int, VmBackup>
     */
    public function dispatchDuePolicies(): Collection
    {
        $queued = new Collection;

        VmBackupPolicy::query()
            ->with('virtualMachine')
            ->where('is_enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->chunk(100, function ($policies) use (&$queued): void {
                foreach ($policies as $policy) {
                    $vm = $policy->virtualMachine;
                    if (! $vm) {
                        continue;
                    }

                    try {
                        $this->assertBackupable($vm);
                        $this->assertNoRunningBackup($vm);
                    } catch (RuntimeException) {
                        $policy->scheduleNext();

                        continue;
                    }

                    $backup = VmBackup::create([
                        'virtual_machine_id' => $vm->id,
                        'vm_backup_policy_id' => $policy->id,
                        'source' => VmBackup::SOURCE_POLICY,
                        'status' => VmBackup::STATUS_QUEUED,
                        'node' => $vm->node,
                    ]);

                    RunVmBackupJob::dispatch($backup->id)->onQueue(RunVmBackupJob::QUEUE);
                    $policy->forceFill(['last_run_at' => now()])->save();
                    $policy->scheduleNext();
                    $queued->push($backup);
                }
            });

        return $queued;
    }

    public function enforceRetention(VirtualMachine $vm, ProxmoxService $proxmox): void
    {
        $policy = $vm->backupPolicy;
        $retention = max(1, (int) ($policy?->retention_count ?? 3));

        $backups = $vm->backups()
            ->where('status', VmBackup::STATUS_READY)
            ->orderByDesc('finished_at')
            ->get();

        foreach ($backups->slice($retention) as $backup) {
            try {
                if ($vm->proxmoxServer && $backup->node && $backup->storage && $backup->volid) {
                    $proxmox->deleteBackupFile($vm->proxmoxServer, $backup->node, $backup->storage, $backup->volid);
                }

                $backup->forceFill([
                    'status' => VmBackup::STATUS_DELETED,
                    'deleted_at' => now(),
                    'error' => null,
                ])->save();
            } catch (\Throwable $exception) {
                $backup->forceFill(['error' => 'Retention cleanup failed: '.$exception->getMessage()])->save();
            }
        }
    }

    public function syncBackupsFromProxmox(): int
    {
        $synced = 0;

        VirtualMachine::query()
            ->with('proxmoxServer')
            ->whereNotNull('vmid')
            ->whereNotNull('node')
            ->chunk(100, function ($vms) use (&$synced): void {
                foreach ($vms as $vm) {
                    if (! $vm->proxmoxServer) {
                        continue;
                    }

                    try {
                        $files = app(ProxmoxService::class)->backupFilesForVm($vm->proxmoxServer, $vm->node, $vm->vmid);
                    } catch (\Throwable) {
                        continue;
                    }

                    foreach ($files as $file) {
                        $volid = $file['volid'] ?? null;
                        if (! $volid) {
                            continue;
                        }

                        VmBackup::updateOrCreate(
                            ['volid' => $volid],
                            [
                                'virtual_machine_id' => $vm->id,
                                'source' => VmBackup::SOURCE_MANUAL,
                                'status' => VmBackup::STATUS_READY,
                                'node' => $vm->node,
                                'storage' => $file['storage'] ?? null,
                                'filename' => $file['filename'] ?? basename((string) $volid),
                                'size_bytes' => (int) ($file['size'] ?? 0),
                                'finished_at' => isset($file['ctime']) ? CarbonImmutable::createFromTimestamp((int) $file['ctime']) : now(),
                                'remote_state' => $file,
                            ],
                        );
                        $synced++;
                    }
                }
            });

        return $synced;
    }

    private function assertBackupable(VirtualMachine $vm): void
    {
        if (! $vm->proxmox_server_id || ! $vm->vmid || ! $vm->node) {
            throw new RuntimeException('این ماشین مجازی هنوز برای بکاپ آماده نیست.');
        }

        if ($vm->provisioning_status !== VirtualMachine::PROVISION_READY) {
            throw new RuntimeException('Backups are available after provisioning is ready.');
        }
    }

    private function assertNoRunningBackup(VirtualMachine $vm): void
    {
        $running = $vm->backups()
            ->whereIn('status', [VmBackup::STATUS_QUEUED, VmBackup::STATUS_RUNNING])
            ->exists();

        if ($running) {
            throw new RuntimeException('یک بکاپ برای این ماشین مجازی در صف یا در حال اجراست.');
        }
    }
}

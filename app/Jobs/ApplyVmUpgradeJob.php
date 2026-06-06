<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Models\VmDisk;
use App\Models\VmUpgradeOrder;
use App\Services\ProxmoxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplyVmUpgradeJob implements ShouldQueue
{
    use Queueable;

    public const QUEUE = 'upgrades';

    private const BUNDLE_RESTART_PAUSE_SECONDS = 5;

    public function __construct(public int $orderId) {}

    public function handle(ProxmoxService $proxmox): void
    {
        $order = VmUpgradeOrder::query()
            ->with(['virtualMachine.proxmoxServer', 'toBundle', 'disk'])
            ->findOrFail($this->orderId);

        if (! $order->isPending()) {
            return;
        }

        $vm = $order->virtualMachine;

        try {
            $order->forceFill(['status' => VmUpgradeOrder::STATUS_APPLYING])->save();

            if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
                throw new \RuntimeException('VM is missing Proxmox server, node, or VMID.');
            }

            $result = match ($order->type) {
                VmUpgradeOrder::TYPE_BUNDLE => $this->applyBundle($order, $vm, $proxmox),
                VmUpgradeOrder::TYPE_EXTRA_DISK => $this->applyExtraDisk($order, $vm, $proxmox),
                VmUpgradeOrder::TYPE_PRIMARY_DISK => $this->applyPrimaryDisk($order, $vm, $proxmox),
                default => throw new \RuntimeException('Unsupported upgrade type: '.$order->type),
            };

            if ($order->type !== VmUpgradeOrder::TYPE_BUNDLE) {
                foreach ($this->taskIds($result) as $taskId) {
                    $proxmox->waitForTask($vm->proxmoxServer, $vm->node, $taskId);
                }
            }

            $config = $proxmox->vmConfig($vm->proxmoxServer, $vm->node, (int) $vm->vmid);
            $this->markSucceeded($order->refresh(), $config, $result);
        } catch (Throwable $exception) {
            $this->markFailed($order->refresh(), $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function applyBundle(VmUpgradeOrder $order, VirtualMachine $vm, ProxmoxService $proxmox): array
    {
        $after = $order->after_snapshot;
        $server = $vm->proxmoxServer;
        $node = (string) $vm->node;
        $vmid = (int) $vm->vmid;
        $result = [
            'restart_required' => true,
            'restart_pause_seconds' => self::BUNDLE_RESTART_PAUSE_SECONDS,
        ];

        $remoteStatus = $proxmox->vmStatus($server, $node, $vmid);
        $result['status_before_shutdown'] = $remoteStatus;

        if (($remoteStatus['status'] ?? null) !== 'stopped') {
            $shutdown = $proxmox->shutdownVm($server, $node, $vmid);
            $result['shutdown'] = $shutdown;
            $this->waitForTaskResult($proxmox, $vm, $shutdown, 180);
            $result['status_after_shutdown'] = $proxmox->waitForVmStopped($server, $node, $vmid, 60);
        }

        $hardware = $proxmox->updateVmHardware($server, $node, $vmid, [
            'cpu_cores' => (int) $after['cpu_cores'],
            'ram_gb' => (int) $after['ram_gb'],
        ]);
        $result['hardware'] = $hardware;
        $result['task_id'] = $hardware['task_id'] ?? null;
        $this->waitForTaskResult($proxmox, $vm, $hardware);

        $currentDisk = (int) ($order->before_snapshot['disk_gb'] ?? $vm->disk_gb);
        $targetDisk = (int) ($after['disk_gb'] ?? $vm->disk_gb);
        if ($targetDisk > $currentDisk) {
            $diskDevice = (string) data_get($vm->desired_state, 'disk_device', $vm->cloudImage?->disk_device ?: 'scsi0');
            $diskResize = $proxmox->resizeDisk($server, $node, $vmid, $diskDevice, $targetDisk);
            $result['disk_resize'] = $diskResize;
            $this->waitForTaskResult($proxmox, $vm, $diskResize);
        }

        $this->pauseBeforeRestart();

        $start = $proxmox->startVm($server, $node, $vmid);
        $result['start'] = $start;
        $this->waitForTaskResult($proxmox, $vm, $start, 180);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function applyPrimaryDisk(VmUpgradeOrder $order, VirtualMachine $vm, ProxmoxService $proxmox): array
    {
        $targetDisk = (int) ($order->after_snapshot['disk_gb'] ?? $vm->disk_gb);
        $diskDevice = (string) data_get($vm->desired_state, 'disk_device', $vm->cloudImage?->disk_device ?: 'scsi0');

        return $proxmox->resizeDisk($vm->proxmoxServer, $vm->node, (int) $vm->vmid, $diskDevice, $targetDisk);
    }

    /**
     * @return array<string, mixed>
     */
    private function applyExtraDisk(VmUpgradeOrder $order, VirtualMachine $vm, ProxmoxService $proxmox): array
    {
        $config = $proxmox->vmConfig($vm->proxmoxServer, $vm->node, (int) $vm->vmid);
        $device = $this->nextScsiDiskDevice($config);
        $result = $proxmox->attachDisk($vm->proxmoxServer, $vm->node, (int) $vm->vmid, [
            'device' => $device,
            'storage' => (string) ($order->after_snapshot['storage'] ?? $vm->storage),
            'size_gb' => (int) $order->after_snapshot['size_gb'],
        ]);
        $result['disk_device'] = $device;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $result
     */
    private function markSucceeded(VmUpgradeOrder $order, array $config, array $result): void
    {
        DB::transaction(function () use ($order, $config, $result): void {
            $locked = VmUpgradeOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $vm = VirtualMachine::query()->whereKey($locked->virtual_machine_id)->lockForUpdate()->firstOrFail();
            $after = $locked->after_snapshot;

            if ($locked->type === VmUpgradeOrder::TYPE_BUNDLE) {
                $vm->forceFill([
                    'vm_bundle_id' => $locked->to_bundle_id,
                    'cpu_cores' => (int) $after['cpu_cores'],
                    'ram_gb' => (int) $after['ram_gb'],
                    'disk_gb' => (int) $after['disk_gb'],
                    'ip_count' => (int) $after['ip_count'],
                    'status' => VirtualMachine::STATUS_RUNNING,
                    'last_stopped_at' => now(),
                    'last_started_at' => now(),
                    'last_billed_at' => now(),
                    'desired_state' => array_merge($vm->desired_state ?? [], ['status' => VirtualMachine::STATUS_RUNNING]),
                    'remote_state' => array_merge($vm->remote_state ?? [], [
                        'upgrade_config' => $config,
                        'upgrade_restart' => $result,
                    ]),
                ])->save();
            }

            if ($locked->type === VmUpgradeOrder::TYPE_PRIMARY_DISK) {
                $vm->forceFill([
                    'disk_gb' => (int) $after['disk_gb'],
                    'last_billed_at' => now(),
                    'remote_state' => array_merge($vm->remote_state ?? [], ['upgrade_config' => $config]),
                ])->save();
            }

            if ($locked->type === VmUpgradeOrder::TYPE_EXTRA_DISK && $locked->disk) {
                $locked->disk->forceFill([
                    'disk_device' => (string) ($result['disk_device'] ?? $locked->disk->disk_device),
                    'status' => VmDisk::STATUS_READY,
                    'last_billed_at' => now(),
                    'remote_state' => $result,
                ])->save();
            }

            $locked->forceFill([
                'status' => VmUpgradeOrder::STATUS_SUCCEEDED,
                'proxmox_task_id' => $result['task_id'] ?? null,
                'failure_reason' => null,
                'applied_at' => now(),
            ])->save();
        });
    }

    private function markFailed(VmUpgradeOrder $order, Throwable $exception): void
    {
        DB::transaction(function () use ($order, $exception): void {
            $locked = VmUpgradeOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->disk) {
                $locked->disk->forceFill(['status' => VmDisk::STATUS_FAILED])->save();
            }

            $locked->forceFill([
                'status' => VmUpgradeOrder::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function waitForTaskResult(ProxmoxService $proxmox, VirtualMachine $vm, array $result, int $timeoutSeconds = 300): void
    {
        if (empty($result['task_id'])) {
            return;
        }

        $proxmox->waitForTask($vm->proxmoxServer, (string) $vm->node, (string) $result['task_id'], $timeoutSeconds);
    }

    private function pauseBeforeRestart(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        sleep(self::BUNDLE_RESTART_PAUSE_SECONDS);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function nextScsiDiskDevice(array $config): string
    {
        for ($slot = 1; $slot <= 30; $slot++) {
            $device = 'scsi'.$slot;

            if (! array_key_exists($device, $config)) {
                return $device;
            }
        }

        throw new \RuntimeException('No free SCSI disk slot is available for this VM.');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, string>
     */
    private function taskIds(array $result): array
    {
        return collect([
            $result['task_id'] ?? null,
            data_get($result, 'disk_resize.task_id'),
        ])->filter()->map(fn (mixed $taskId): string => (string) $taskId)->unique()->values()->all();
    }
}

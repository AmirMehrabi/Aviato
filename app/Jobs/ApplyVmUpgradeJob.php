<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\VirtualMachine;
use App\Models\VmBundleLocationMapping;
use App\Models\VmDisk;
use App\Models\VmUpgradeOrder;
use App\Services\HetznerCloudService;
use App\Services\ProxmoxService;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplyVmUpgradeJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const QUEUE = 'upgrades';

    private const BUNDLE_RESTART_PAUSE_SECONDS = 5;

    public int $timeout = 900;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 1200;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('vm-upgrade-'.$this->orderId))->dontRelease()->expireAfter(1200)];
    }

    public function handle(ProxmoxService $proxmox, ?WalletService $wallets = null, ?HetznerCloudService $hetzner = null): void
    {
        $wallets ??= app(WalletService::class);

        $order = VmUpgradeOrder::query()
            ->with(['virtualMachine.proxmoxServer', 'virtualMachine.infrastructureLocation.hetznerAccount', 'virtualMachine.project.owner', 'virtualMachine.customer', 'toBundle', 'disk'])
            ->findOrFail($this->orderId);

        if (! $order->isPending()) {
            return;
        }

        $order->forceFill([
            'status' => VmUpgradeOrder::STATUS_APPLYING,
            'last_attempt_at' => now(),
            'reconcile_after' => null,
        ])->save();

        $vm = $order->virtualMachine;
        $billingCustomer = $vm->project?->owner ?? $vm->customer;

        try {
            if ($vm->isHetzner()) {
                if ($order->type !== VmUpgradeOrder::TYPE_BUNDLE) {
                    throw new \RuntimeException('This upgrade type is not supported for Hetzner machines.');
                }

                $result = $this->applyHetznerBundle($order, $vm, $hetzner ?? app(HetznerCloudService::class), $billingCustomer, $wallets);
                $this->markSucceeded($order->refresh(), $result['server'] ?? [], $result);

                return;
            }

            if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
                throw new \RuntimeException('VM is missing Proxmox server, node, or VMID.');
            }

            $result = match ($order->type) {
                VmUpgradeOrder::TYPE_BUNDLE => $this->applyBundle($order, $vm, $proxmox, $billingCustomer, $wallets),
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
            if ($order->type === VmUpgradeOrder::TYPE_BUNDLE && ! $this->bundleConfigMatches($vm, $order, $config)) {
                throw new \RuntimeException('Proxmox configuration does not yet match the requested bundle.');
            }
            $this->markSucceeded($order->refresh(), $config, $result);
        } catch (Throwable $exception) {
            $this->reconcileAfterFailure($order->refresh(), $proxmox, $exception);
        }
    }

    private function applyHetznerBundle(VmUpgradeOrder $order, VirtualMachine $vm, HetznerCloudService $hetzner, ?Customer $billingCustomer, WalletService $wallets): array
    {
        $account = $vm->infrastructureLocation?->hetznerAccount;

        if (! $account || ! $vm->remote_id || ! $order->toBundle) {
            throw new \RuntimeException('VM is missing Hetzner account, remote ID, or target bundle.');
        }

        $mapping = VmBundleLocationMapping::query()
            ->with('hetznerServerType')
            ->where('infrastructure_location_id', $vm->infrastructure_location_id)
            ->where('vm_bundle_id', $order->toBundle->id)
            ->where('is_active', true)
            ->first();

        $serverType = $mapping?->hetznerServerType;
        if (! $serverType) {
            throw new \RuntimeException('No Hetzner server type is mapped for the target bundle.');
        }

        $billingBlocked = $billingCustomer ? $wallets->isWalletDepleted($billingCustomer) : false;
        $remoteBefore = $hetzner->server($account, $vm->remote_id);
        $result = ['server_before' => $remoteBefore, 'server_type' => $serverType->name];

        if (($remoteBefore['status'] ?? null) === 'running') {
            $shutdown = $hetzner->shutdown($account, $vm->remote_id);
            $result['shutdown'] = $shutdown;
            $hetzner->waitForAction($account, $shutdown['action']['id'] ?? null, 180);
        }

        $change = $hetzner->changeType($account, $vm->remote_id, $serverType->name, true);
        $result['change_type'] = $change;
        $hetzner->waitForAction($account, $change['action']['id'] ?? null, 300);

        if (! $billingBlocked) {
            $start = $hetzner->powerOn($account, $vm->remote_id);
            $result['start'] = $start;
            $hetzner->waitForAction($account, $start['action']['id'] ?? null, 180);
        } else {
            $result['start_skipped_wallet_locked'] = true;
        }

        $result['server'] = $hetzner->server($account, $vm->remote_id) ?? [];

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function applyBundle(VmUpgradeOrder $order, VirtualMachine $vm, ProxmoxService $proxmox, ?Customer $billingCustomer, WalletService $wallets): array
    {
        $after = $order->after_snapshot;
        $server = $vm->proxmoxServer;
        $node = (string) $vm->node;
        $vmid = (int) $vm->vmid;
        $result = [
            'restart_required' => true,
            'restart_pause_seconds' => self::BUNDLE_RESTART_PAUSE_SECONDS,
        ];
        $billingBlocked = $billingCustomer ? $wallets->isWalletDepleted($billingCustomer) : false;
        $diskDevice = (string) data_get($vm->desired_state, 'disk_device', $vm->cloudImage?->disk_device ?: 'scsi0');
        $config = $proxmox->vmConfig($server, $node, $vmid);
        $hardwareMatches = $this->hardwareMatches($config, $after);
        $currentDisk = $this->diskSizeGb($config, $diskDevice)
            ?? (int) ($order->before_snapshot['disk_gb'] ?? $vm->disk_gb);
        $targetDisk = (int) ($after['disk_gb'] ?? $vm->disk_gb);
        $requiresRemoteChange = ! $hardwareMatches || $currentDisk < $targetDisk;

        $remoteStatus = $proxmox->vmStatus($server, $node, $vmid);
        $result['status_before_shutdown'] = $remoteStatus;
        $result['config_before'] = $config;

        if ($requiresRemoteChange && ($remoteStatus['status'] ?? null) !== 'stopped') {
            $shutdown = $proxmox->shutdownVm($server, $node, $vmid, context: [
                'source' => 'upgrade_job',
                'virtual_machine_id' => $vm->id,
                'upgrade_order_id' => $order->id,
            ]);
            $result['shutdown'] = $shutdown;
            $this->waitForTaskResult($proxmox, $vm, $shutdown, 180);
            $result['status_after_shutdown'] = $proxmox->waitForVmStopped($server, $node, $vmid, 60);
            $this->checkpoint($order, 'shutdown', $shutdown);
        }

        if (! $hardwareMatches) {
            $hardware = $proxmox->updateVmHardware($server, $node, $vmid, [
                'cpu_cores' => (int) $after['cpu_cores'],
                'ram_gb' => (int) $after['ram_gb'],
            ]);
            $result['hardware'] = $hardware;
            $result['task_id'] = $hardware['task_id'] ?? null;
            $this->checkpoint($order, 'hardware_submitted', $hardware);
            $this->waitForTaskResult($proxmox, $vm, $hardware);
            $this->checkpoint($order, 'hardware_applied', $hardware);
        } else {
            $result['hardware_already_applied'] = true;
        }

        if ($currentDisk < $targetDisk) {
            $diskResize = $proxmox->resizeDisk($server, $node, $vmid, $diskDevice, $targetDisk);
            $result['disk_resize'] = $diskResize;
            $this->checkpoint($order, 'disk_submitted', $diskResize);
            $this->waitForTaskResult($proxmox, $vm, $diskResize);
            $this->checkpoint($order, 'disk_applied', $diskResize);
        } elseif ($targetDisk > (int) ($order->before_snapshot['disk_gb'] ?? $vm->disk_gb)) {
            $result['disk_already_applied'] = true;
        }

        if ($requiresRemoteChange) {
            $this->pauseBeforeRestart();
        }

        $currentStatus = $proxmox->vmStatus($server, $node, $vmid);
        if (! $billingBlocked && ($currentStatus['status'] ?? null) !== 'running') {
            $start = $proxmox->startVm($server, $node, $vmid);
            $result['start'] = $start;
            $this->checkpoint($order, 'start_submitted', $start);
            $this->waitForTaskResult($proxmox, $vm, $start, 180);
            $this->checkpoint($order, 'started', $start);
        } else {
            $result[$billingBlocked ? 'start_skipped_wallet_locked' : 'already_running'] = true;
        }

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
                    'status' => ! empty($result['start_skipped_wallet_locked']) ? VirtualMachine::STATUS_STOPPED : VirtualMachine::STATUS_RUNNING,
                    'last_stopped_at' => now(),
                    'last_started_at' => empty($result['start_skipped_wallet_locked']) ? now() : $vm->last_started_at,
                    'last_billed_at' => now(),
                    'desired_state' => array_merge($vm->desired_state ?? [], ['status' => ! empty($result['start_skipped_wallet_locked']) ? VirtualMachine::STATUS_STOPPED : VirtualMachine::STATUS_RUNNING]),
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
                'progress' => array_merge($locked->progress ?? [], ['completed_at' => now()->toIso8601String()]),
                'failure_reason' => null,
                'reconcile_after' => null,
                'applied_at' => now(),
            ])->save();
        });
    }

    private function reconcileAfterFailure(VmUpgradeOrder $order, ProxmoxService $proxmox, Throwable $exception): void
    {
        $vm = $order->virtualMachine;

        if ($vm?->isProxmox() && $vm->proxmoxServer && $vm->node && $vm->vmid && $order->type === VmUpgradeOrder::TYPE_BUNDLE) {
            try {
                $config = $proxmox->vmConfig($vm->proxmoxServer, (string) $vm->node, (int) $vm->vmid);

                if ($this->bundleConfigMatches($vm, $order, $config)) {
                    $status = $proxmox->vmStatus($vm->proxmoxServer, (string) $vm->node, (int) $vm->vmid);
                    $this->markSucceeded($order, $config, [
                        'reconciled_after_error' => true,
                        'original_error' => $exception->getMessage(),
                        'start_skipped_wallet_locked' => ($status['status'] ?? null) !== 'running',
                    ]);

                    return;
                }
            } catch (Throwable) {
                // The remote outcome is unknown. The scheduled reconciler will try again.
            }
        }

        DB::transaction(function () use ($order, $exception): void {
            $locked = VmUpgradeOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'status' => VmUpgradeOrder::STATUS_RECONCILIATION_REQUIRED,
                'failure_reason' => $exception->getMessage(),
                'reconcile_after' => now()->addMinutes(2),
            ])->save();
        });
    }

    /** @param array<string, mixed> $details */
    private function checkpoint(VmUpgradeOrder $order, string $step, array $details = []): void
    {
        $fresh = VmUpgradeOrder::query()->findOrFail($order->id);
        $progress = $fresh->progress ?? [];
        $progress[$step] = ['at' => now()->toIso8601String(), 'details' => $details];
        $fresh->forceFill(['progress' => $progress, 'last_attempt_at' => now()])->save();
        $order->setRawAttributes($fresh->getAttributes(), true);
    }

    /** @param array<string, mixed> $config @param array<string, mixed> $after */
    private function hardwareMatches(array $config, array $after): bool
    {
        return (int) ($config['cores'] ?? 0) === (int) ($after['cpu_cores'] ?? 0)
            && (int) ($config['memory'] ?? 0) === (int) ($after['ram_gb'] ?? 0) * 1024;
    }

    /** @param array<string, mixed> $config */
    private function bundleConfigMatches(VirtualMachine $vm, VmUpgradeOrder $order, array $config): bool
    {
        $after = $order->after_snapshot;
        $diskDevice = (string) data_get($vm->desired_state, 'disk_device', $vm->cloudImage?->disk_device ?: 'scsi0');
        $diskSize = $this->diskSizeGb($config, $diskDevice);

        return $this->hardwareMatches($config, $after)
            && $diskSize !== null
            && $diskSize >= (int) ($after['disk_gb'] ?? 0);
    }

    /** @param array<string, mixed> $config */
    private function diskSizeGb(array $config, string $device): ?int
    {
        $value = $config[$device] ?? null;
        if (is_array($value)) {
            $value = $value['size'] ?? null;
        }
        if (! is_scalar($value)) {
            return null;
        }

        $disk = (string) $value;
        if (preg_match('/(?:^|[,=:])size[=:]?(\d+(?:\.\d+)?)G(?:,|$)/i', $disk, $matches) === 1
            || preg_match('/:(\d+(?:\.\d+)?)G?(?:,|$)/i', $disk, $matches) === 1) {
            return (int) ceil((float) $matches[1]);
        }

        return null;
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

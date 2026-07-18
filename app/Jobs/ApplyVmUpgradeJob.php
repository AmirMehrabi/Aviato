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

    public function handle(ProxmoxService $proxmox, ?WalletService $wallets = null, ?HetznerCloudService $hetzner = null): void
    {
        $wallets ??= app(WalletService::class);

        $order = VmUpgradeOrder::query()
            ->with(['virtualMachine.proxmoxServer', 'virtualMachine.infrastructureLocation.hetznerAccount', 'virtualMachine.project.owner', 'virtualMachine.customer', 'toBundle', 'disk'])
            ->findOrFail($this->orderId);

        if (! $order->isPending()) {
            return;
        }

        $vm = $order->virtualMachine;
        $billingCustomer = $vm->project?->owner ?? $vm->customer;

        try {
            $order->forceFill(['status' => VmUpgradeOrder::STATUS_APPLYING])->save();

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
            $this->markSucceeded($order->refresh(), $config, $result);
        } catch (Throwable $exception) {
            $this->markFailed($order->refresh(), $exception);
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

        $remoteStatus = $proxmox->vmStatus($server, $node, $vmid);
        $result['status_before_shutdown'] = $remoteStatus;

        if (($remoteStatus['status'] ?? null) !== 'stopped') {
            $shutdown = $proxmox->shutdownVm($server, $node, $vmid, context: [
                'source' => 'upgrade_job',
                'virtual_machine_id' => $vm->id,
                'upgrade_order_id' => $order->id,
            ]);
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

        if (! $billingBlocked) {
            $start = $proxmox->startVm($server, $node, $vmid);
            $result['start'] = $start;
            $this->waitForTaskResult($proxmox, $vm, $start, 180);
        } else {
            $result['start_skipped_wallet_locked'] = true;
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

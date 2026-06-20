<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\HetznerCloudService;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RebuildCloudVirtualMachine implements ShouldBeUnique, ShouldQueue
{
    use FoundationQueueable;

    public const QUEUE = 'provisioning';

    public int $timeout = 1200;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 90, 180];

    public int $uniqueFor = 1200;

    public function __construct(public readonly int $virtualMachineId) {}

    public function uniqueId(): string
    {
        return 'rebuild-vm-'.$this->virtualMachineId;
    }

    public function handle(ProxmoxService $proxmox, IpPoolService $ipPools, ?WalletService $wallets = null, ?HetznerCloudService $hetzner = null): void
    {
        $wallets ??= app(WalletService::class);

        $vm = VirtualMachine::query()
            ->with(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool', 'project.owner', 'customer', 'infrastructureLocation.hetznerAccount'])
            ->findOrFail($this->virtualMachineId);

        if ($vm->isHetzner()) {
            $this->handleHetzner($vm, $wallets, $hetzner ?? app(HetznerCloudService::class));

            return;
        }

        $server = $vm->proxmoxServer;
        $image = $vm->cloudImage;
        $address = $vm->reservedIpAddress;
        $history = data_get($vm->remote_state, 'rebuild_steps', []);
        $billingCustomer = $vm->project?->owner ?? $vm->customer;

        Log::info('Cloud VM rebuild job started', [
            'virtual_machine_id' => $vm->id,
            'uuid' => $vm->uuid,
            'proxmox_server_id' => $vm->proxmox_server_id,
            'node' => $vm->node,
            'vmid' => $vm->vmid,
            'attempt' => $this->attempts(),
        ]);

        $retryable = true;

        try {
            if (! $server || ! $image || ! $vm->node || ! $vm->vmid || ! $vm->template_vmid) {
                $retryable = false;
                throw new RuntimeException('VM is missing Proxmox server, cloud image, node, VMID, or template data.');
            }

            $node = $vm->node;
            $vmid = (int) $vm->vmid;

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_PENDING,
                'provisioning_task_id' => null,
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'rebuild_started_at' => data_get($vm->remote_state, 'rebuild_started_at') ?: now()->toISOString(),
                    'rebuild_error' => null,
                    'rebuild_steps' => $history,
                ]),
            ])->save();

            $config = $proxmox->vmConfigOrNull($server, $node, $vmid);
            $history[] = ['step' => 'config', 'result' => $config, 'at' => now()->toISOString()];

            if ($config !== null && ! $this->remoteVmMatchesPanelVm($vm, $config)) {
                $retryable = false;
                throw new RuntimeException('Remote Proxmox VMID '.$vmid.' does not belong to this panel VM.');
            }

            if ($config !== null) {
                $remoteStatus = $proxmox->vmStatus($server, $node, $vmid);
                $history[] = ['step' => 'status', 'result' => $remoteStatus, 'at' => now()->toISOString()];
                $this->recordHistory($vm, $history);

                if (($remoteStatus['status'] ?? null) === 'running') {
                    $shutdown = $proxmox->shutdownVm($server, $node, $vmid);
                    $history[] = ['step' => 'shutdown', 'result' => $shutdown, 'at' => now()->toISOString()];
                    $vm->forceFill(['provisioning_task_id' => $shutdown['task_id'] ?? null])->save();
                    if (! empty($shutdown['task_id'])) {
                        $history[] = ['step' => 'shutdown_wait', 'result' => $proxmox->waitForTask($server, $node, (string) $shutdown['task_id'], 180), 'at' => now()->toISOString()];
                    }
                    $history[] = ['step' => 'status_after_shutdown', 'result' => $proxmox->waitForVmStopped($server, $node, $vmid, 60), 'at' => now()->toISOString()];
                }

                $delete = $proxmox->deleteVm($server, $node, $vmid, true);
                $history[] = ['step' => 'delete', 'result' => $delete, 'at' => now()->toISOString()];
                $vm->forceFill(['provisioning_task_id' => $delete['task_id'] ?? null])->save();
                if (! empty($delete['task_id'])) {
                    $history[] = ['step' => 'delete_wait', 'result' => $proxmox->waitForTask($server, $node, (string) $delete['task_id'], 300), 'at' => now()->toISOString()];
                }
            } else {
                $history[] = ['step' => 'remote_missing', 'result' => 'will_recreate_same_vmid', 'at' => now()->toISOString()];
            }

            $clone = $proxmox->cloneCloudTemplate($server, [
                'node' => $node,
                'template_vmid' => $vm->template_vmid,
                'newid' => $vmid,
                'name' => $vm->name,
                'storage' => $vm->storage,
                'description' => $this->identityDescription($vm),
            ]);
            $history[] = ['step' => 'clone', 'result' => $clone, 'at' => now()->toISOString()];
            $vm->forceFill(['provisioning_task_id' => $clone['task_id'] ?? null])->save();
            if (! empty($clone['task_id'])) {
                $history[] = ['step' => 'clone_wait', 'result' => $proxmox->waitForTask($server, $node, (string) $clone['task_id'], 300), 'at' => now()->toISOString()];
            }

            $cloudInitEnabled = (bool) $image->cloud_init_enabled;
            $configResult = $proxmox->configureCloudInit($server, [
                'node' => $node,
                'vmid' => $vmid,
                'cpu_cores' => $vm->cpu_cores,
                'ram_gb' => $vm->ram_gb,
                'login_username' => $cloudInitEnabled ? $vm->login_username : null,
                'login_password' => $cloudInitEnabled ? $vm->login_password : null,
                'ssh_public_key' => $cloudInitEnabled ? $vm->ssh_public_key : null,
                'ipconfig0' => ($cloudInitEnabled && $address) ? $ipPools->ipConfig($address) : null,
                'nameserver' => ($cloudInitEnabled && $address) ? $ipPools->nameservers($address) : null,
                'cicustom' => $cloudInitEnabled ? 'vendor=local:snippets/ubuntu-password-login.yml' : null,
                'network_bridge' => $vm->network_bridge ?: 'vmbr1',
                'onboot' => false,
                'description' => $this->identityDescription($vm),
            ]);
            $history[] = ['step' => 'config', 'result' => $configResult, 'at' => now()->toISOString()];

            $resize = $proxmox->resizeDisk($server, $node, $vmid, $image->disk_device, $vm->disk_gb);
            $history[] = ['step' => 'resize', 'result' => $resize, 'at' => now()->toISOString()];
            if (! empty($resize['task_id'])) {
                $history[] = ['step' => 'resize_wait', 'result' => $proxmox->waitForTask($server, $node, (string) $resize['task_id']), 'at' => now()->toISOString()];
            }

            if ($cloudInitEnabled) {
                $cloudInit = $proxmox->regenerateCloudInit($server, $node, $vmid);
                $history[] = ['step' => 'cloudinit_regenerate', 'result' => $cloudInit, 'at' => now()->toISOString()];
                if (! empty($cloudInit['task_id'])) {
                    $history[] = ['step' => 'cloudinit_regenerate_wait', 'result' => $proxmox->waitForTask($server, $node, (string) $cloudInit['task_id']), 'at' => now()->toISOString()];
                }
            }

            if ($address) {
                $antiSpoofing = $proxmox->applyVmIpAntiSpoofing($server, $node, $vmid, $address->address, 'net0', $vm->network_bridge ?: 'vmbr1');
                $history[] = ['step' => 'anti_spoofing', 'result' => $antiSpoofing, 'at' => now()->toISOString()];

                if (! empty($antiSpoofing['mac_address'])) {
                    $vm->forceFill(['mac_address' => $antiSpoofing['mac_address']])->save();
                }
            }

            $startAllowed = ! $billingCustomer || ! $wallets->isBelowNegativeThreshold($billingCustomer);
            if ($startAllowed) {
                $start = $proxmox->startVm($server, $node, $vmid);
                $history[] = ['step' => 'start', 'result' => $start, 'at' => now()->toISOString()];
                if (! empty($start['task_id'])) {
                    $history[] = ['step' => 'start_wait', 'result' => $proxmox->waitForTask($server, $node, (string) $start['task_id']), 'at' => now()->toISOString()];
                }
            } else {
                $history[] = ['step' => 'start_skipped_wallet_locked', 'result' => 'wallet below threshold', 'at' => now()->toISOString()];
            }

            $verifiedConfig = $proxmox->vmConfig($server, $node, $vmid);
            $history[] = ['step' => 'config_verify', 'result' => $verifiedConfig, 'at' => now()->toISOString()];

            if ($address) {
                $ipPools->assign($address, $vm);
            }

            $verifiedMacAddress = filled($verifiedConfig['net0'] ?? null)
                ? $proxmox->macAddressFromNetworkDevice((string) $verifiedConfig['net0'])
                : null;

            $vm->forceFill([
                'mac_address' => $vm->mac_address ?: $verifiedMacAddress,
                'status' => $startAllowed ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'provisioning_task_id' => null,
                'last_started_at' => $startAllowed ? now() : $vm->last_started_at,
                'last_seen_at' => now(),
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'rebuild_steps' => $history,
                    'rebuild_finished_at' => now()->toISOString(),
                    'rebuild_error' => null,
                ]),
            ])->save();
        } catch (Throwable $exception) {
            $hasAttemptsRemaining = $retryable && $this->hasAttemptsRemaining();
            $history[] = ['step' => 'failed', 'error' => $exception->getMessage(), 'at' => now()->toISOString()];

            Log::error('Cloud VM rebuild failed', [
                'virtual_machine_id' => $vm->id,
                'vmid' => $vm->vmid,
                'attempt' => $this->attempts(),
                'will_retry' => $hasAttemptsRemaining,
                'message' => $exception->getMessage(),
            ]);

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => $hasAttemptsRemaining
                    ? VirtualMachine::PROVISION_PENDING
                    : VirtualMachine::PROVISION_FAILED,
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'rebuild_steps' => $history,
                    'rebuild_error' => $exception->getMessage(),
                    $hasAttemptsRemaining ? 'rebuild_retrying_at' : 'rebuild_failed_at' => now()->toISOString(),
                ]),
            ])->save();

            if ($hasAttemptsRemaining) {
                throw $exception;
            }
        }
    }

    private function handleHetzner(VirtualMachine $vm, WalletService $wallets, HetznerCloudService $hetzner): void
    {
        $account = $vm->infrastructureLocation?->hetznerAccount;
        $image = $vm->cloudImage;
        $history = data_get($vm->remote_state, 'rebuild_steps', []);
        $billingCustomer = $vm->project?->owner ?? $vm->customer;

        try {
            if (! $account || ! $image || ! $vm->remote_id || ! $image->remote_image_id) {
                throw new RuntimeException('VM is missing Hetzner account, image, or remote server ID.');
            }

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_PENDING,
                'provisioning_task_id' => null,
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'rebuild_started_at' => data_get($vm->remote_state, 'rebuild_started_at') ?: now()->toISOString(),
                    'rebuild_error' => null,
                    'rebuild_steps' => $history,
                ]),
            ])->save();

            $remoteBefore = $hetzner->server($account, $vm->remote_id);
            $history[] = ['step' => 'status', 'result' => $remoteBefore, 'at' => now()->toISOString()];

            if (($remoteBefore['status'] ?? null) === 'running') {
                $shutdown = $hetzner->shutdown($account, $vm->remote_id);
                $history[] = ['step' => 'shutdown', 'result' => $shutdown, 'at' => now()->toISOString()];
                $hetzner->waitForAction($account, $shutdown['action']['id'] ?? null, 180);
            }

            $rebuild = $hetzner->rebuild($account, $vm->remote_id, (string) $image->remote_image_id);
            $history[] = ['step' => 'rebuild', 'result' => $rebuild, 'at' => now()->toISOString()];
            $hetzner->waitForAction($account, $rebuild['action']['id'] ?? null, 600);

            $startAllowed = ! $billingCustomer || ! $wallets->isBelowNegativeThreshold($billingCustomer);
            if ($startAllowed) {
                $start = $hetzner->powerOn($account, $vm->remote_id);
                $history[] = ['step' => 'start', 'result' => $start, 'at' => now()->toISOString()];
                $hetzner->waitForAction($account, $start['action']['id'] ?? null, 180);
            }

            $server = $hetzner->server($account, $vm->remote_id) ?? [];

            $vm->forceFill([
                'remote_name' => $server['name'] ?? $vm->remote_name,
                'ip_address' => data_get($server, 'public_net.ipv4.ip') ?: $vm->ip_address,
                'status' => $startAllowed ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'provisioning_task_id' => null,
                'last_started_at' => $startAllowed ? now() : $vm->last_started_at,
                'last_seen_at' => now(),
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'rebuild_steps' => $history,
                    'rebuild_finished_at' => now()->toISOString(),
                    'rebuild_error' => null,
                    'hetzner_server' => $server,
                ]),
            ])->save();
        } catch (Throwable $exception) {
            $hasAttemptsRemaining = $this->hasAttemptsRemaining();
            $history[] = ['step' => 'failed', 'error' => $exception->getMessage(), 'at' => now()->toISOString()];

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => $hasAttemptsRemaining ? VirtualMachine::PROVISION_PENDING : VirtualMachine::PROVISION_FAILED,
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'rebuild_steps' => $history,
                    'rebuild_error' => $exception->getMessage(),
                    $hasAttemptsRemaining ? 'rebuild_retrying_at' : 'rebuild_failed_at' => now()->toISOString(),
                ]),
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
            'remote_state' => array_merge($vm->remote_state ?? [], ['rebuild_steps' => $history]),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function remoteVmMatchesPanelVm(VirtualMachine $vm, array $config): bool
    {
        if (($config['name'] ?? null) !== $vm->name) {
            return false;
        }

        $description = (string) ($config['description'] ?? '');
        if ($description === '') {
            return true;
        }

        if (preg_match('/Aviato panel VM #(\d+) /', $description, $matches)) {
            return (int) $matches[1] === (int) $vm->id;
        }

        return true;
    }

    private function identityDescription(VirtualMachine $vm): string
    {
        return 'Aviato panel VM #'.$vm->id.' uuid '.$vm->uuid.' rebuilt for customer #'.$vm->customer_id;
    }

    private function hasAttemptsRemaining(): bool
    {
        return $this->job !== null && $this->attempts() < $this->tries;
    }
}

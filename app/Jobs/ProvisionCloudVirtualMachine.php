<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Models\VmBundleLocationMapping;
use App\Services\HetznerCloudService;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProvisionCloudVirtualMachine implements ShouldQueue
{
    use FoundationQueueable;

    public const QUEUE = 'provisioning';

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 90, 180];

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly int $virtualMachineId,
        public readonly array $options = [],
    ) {}

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
        $billingCustomer = $vm->project?->owner ?? $vm->customer;

        if (! $server || ! $image) {
            throw new RuntimeException('Provisioning cannot continue without Proxmox server and cloud image.');
        }

        $networkBridge = $vm->network_bridge ?: 'vmbr1';
        $address = $vm->reservedIpAddress;

        $history = data_get($vm->remote_state, 'steps', []);
        $remoteCreated = false;

        try {
            $vmid = $this->provisioningVmid($proxmox, $vm);
            $remoteCreated = $vm->vmid
                && (int) $vm->vmid === $vmid
                && $this->remoteVmMatchesPanelVm($proxmox, $vm, $vmid);

            $vm->forceFill([
                'vmid' => $vmid,
                'provisioning_task_id' => null,
                'remote_state' => ['steps' => $history],
            ])->save();

            if ($remoteCreated) {
                $history[] = ['step' => 'clone_resume', 'result' => ['vmid' => $vmid], 'at' => now()->toISOString()];
            } else {
                $clone = $proxmox->cloneCloudTemplate($server, [
                    'node' => $vm->node,
                    'template_vmid' => $vm->template_vmid,
                    'newid' => $vmid,
                    'name' => $vm->name,
                    'storage' => $vm->storage,
                    'description' => 'Created from Aviato panel for customer #'.$vm->customer_id,
                ]);
                $history[] = ['step' => 'clone', 'result' => $clone];
                $vm->forceFill(['provisioning_task_id' => $clone['task_id'] ?? null, 'remote_state' => ['steps' => $history]])->save();
                if (! empty($clone['task_id'])) {
                    $history[] = ['step' => 'clone_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $clone['task_id'])];
                }
                $remoteCreated = true;
            }

            $cloudInitEnabled = (bool) $image->cloud_init_enabled;
            $shouldStartAfterCreate = (bool) ($this->options['start_after_create'] ?? true);
            $canAutoStart = ! $billingCustomer || ! $wallets->isBelowNegativeThreshold($billingCustomer);

            if (! $cloudInitEnabled) {
                $verifiedConfig = $proxmox->vmConfig($server, $vm->node, $vmid);
                $history[] = ['step' => 'config_verify', 'result' => $verifiedConfig];

                $actualCpu = (int) ($verifiedConfig['cores'] ?? $vm->cpu_cores);
                $actualRamMb = (int) ($verifiedConfig['memory'] ?? ($vm->ram_gb * 1024));
                $actualDiskGb = (int) ($verifiedConfig['maxdisk'] ?? ($vm->disk_gb * 1024 * 1024 * 1024)) / (1024 * 1024 * 1024);

                $verifiedMacAddress = filled($verifiedConfig['net0'] ?? null)
                    ? $proxmox->macAddressFromNetworkDevice((string) $verifiedConfig['net0'])
                    : null;

                if ($shouldStartAfterCreate && $canAutoStart) {
                    $start = $proxmox->startVm($server, $vm->node, $vmid);
                    $history[] = ['step' => 'start', 'result' => $start];
                    if (! empty($start['task_id'])) {
                        $history[] = ['step' => 'start_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $start['task_id'])];
                    }
                } else {
                    $history[] = ['step' => 'start_skipped_wallet_locked', 'result' => 'wallet below threshold'];
                }

                $vm->forceFill([
                    'mac_address' => $vm->mac_address ?: $verifiedMacAddress,
                    'cpu_cores' => $actualCpu,
                    'ram_gb' => (int) ceil($actualRamMb / 1024),
                    'disk_gb' => $actualDiskGb,
                    'ip_count' => 0,
                    'status' => $shouldStartAfterCreate && $canAutoStart ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                    'provisioning_status' => VirtualMachine::PROVISION_READY,
                    'last_started_at' => $shouldStartAfterCreate && $canAutoStart ? now() : null,
                    'last_billed_at' => now(),
                    'last_seen_at' => now(),
                    'remote_state' => ['steps' => $history, 'finished_at' => now()->toISOString()],
                ])->save();

                $vm->desired_state = $vm->desiredStateSnapshot() + [
                    'start_after_create' => $shouldStartAfterCreate,
                    'onboot' => $this->options['onboot'] ?? false,
                    'disk_device' => $image->disk_device,
                ];
                $vm->save();

                return;
            }

            $config = $proxmox->configureCloudInit($server, [
                'node' => $vm->node,
                'vmid' => $vmid,
                'cpu_cores' => $vm->cpu_cores,
                'ram_gb' => $vm->ram_gb,
                'login_username' => $vm->login_username,
                'login_password' => $vm->login_password,
                'ssh_public_key' => $vm->ssh_public_key,
                'ipconfig0' => $address ? $ipPools->ipConfig($address) : null,
                'nameserver' => $address ? $ipPools->nameservers($address) : null,
                'cicustom' => 'vendor=local:snippets/ubuntu-password-login.yml',
                'network_bridge' => $networkBridge,
                'onboot' => $this->options['onboot'] ?? false,
                'description' => 'Cloud-init configured by Aviato panel',
            ]);
            $history[] = ['step' => 'config', 'result' => $config];

            $resize = $proxmox->resizeDisk($server, $vm->node, $vmid, $image->disk_device, $vm->disk_gb);
            $history[] = ['step' => 'resize', 'result' => $resize];
            if (! empty($resize['task_id'])) {
                $history[] = ['step' => 'resize_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $resize['task_id'])];
            }

            $cloudInit = $proxmox->regenerateCloudInit($server, $vm->node, $vmid);
            $history[] = ['step' => 'cloudinit_regenerate', 'result' => $cloudInit];
            if (! empty($cloudInit['task_id'])) {
                $history[] = ['step' => 'cloudinit_regenerate_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $cloudInit['task_id'])];
            }

            if ($address) {
                $antiSpoofing = $proxmox->applyVmIpAntiSpoofing($server, $vm->node, $vmid, $address->address, 'net0', $networkBridge);
                $history[] = ['step' => 'anti_spoofing', 'result' => $antiSpoofing];

                if (! empty($antiSpoofing['mac_address'])) {
                    $vm->forceFill(['mac_address' => $antiSpoofing['mac_address']])->save();
                }
            }

            if ($shouldStartAfterCreate) {
                if ($canAutoStart) {
                    $start = $proxmox->startVm($server, $vm->node, $vmid);
                    $history[] = ['step' => 'start', 'result' => $start];
                    if (! empty($start['task_id'])) {
                        $history[] = ['step' => 'start_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $start['task_id'])];
                    }
                } else {
                    $history[] = ['step' => 'start_skipped_wallet_locked', 'result' => 'wallet below threshold'];
                }
            }

            $verifiedConfig = $proxmox->vmConfig($server, $vm->node, $vmid);
            $history[] = ['step' => 'config_verify', 'result' => $verifiedConfig];

            if ($address) {
                $ipPools->assign($address, $vm);
            }

            $verifiedMacAddress = filled($verifiedConfig['net0'] ?? null)
                ? $proxmox->macAddressFromNetworkDevice((string) $verifiedConfig['net0'])
                : null;

            $vm->forceFill([
                'mac_address' => $vm->mac_address ?: $verifiedMacAddress,
                'status' => $shouldStartAfterCreate && $canAutoStart ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'last_started_at' => $shouldStartAfterCreate && $canAutoStart ? now() : null,
                'last_billed_at' => now(),
                'last_seen_at' => now(),
                'remote_state' => ['steps' => $history, 'finished_at' => now()->toISOString()],
            ])->save();
        } catch (Throwable $exception) {
            $hasAttemptsRemaining = $this->hasAttemptsRemaining();

            Log::error('Cloud VM provisioning failed', [
                'virtual_machine_id' => $vm->id,
                'vmid' => $vm->vmid,
                'attempt' => $this->attempts(),
                'will_retry' => $hasAttemptsRemaining,
                'message' => $exception->getMessage(),
            ]);

            if ($address && ! $hasAttemptsRemaining && ! $this->remoteVmMatchesPanelVm($proxmox, $vm, (int) $vm->vmid)) {
                $ipPools->release($address);
            }

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => $hasAttemptsRemaining
                    ? VirtualMachine::PROVISION_PENDING
                    : VirtualMachine::PROVISION_FAILED,
                'remote_state' => [
                    'steps' => $history,
                    'error' => $exception->getMessage(),
                    $hasAttemptsRemaining ? 'retrying_at' : 'failed_at' => now()->toISOString(),
                    'attempt' => $this->attempts(),
                ],
            ])->save();

            throw $exception;
        }

    }

    private function handleHetzner(VirtualMachine $vm, WalletService $wallets, HetznerCloudService $hetzner): void
    {
        $location = $vm->infrastructureLocation;
        $account = $location?->hetznerAccount;
        $image = $vm->cloudImage;
        $billingCustomer = $vm->project?->owner ?? $vm->customer;
        $history = data_get($vm->remote_state, 'steps', []);

        if (! $location || ! $account || ! $image) {
            throw new RuntimeException('Provisioning cannot continue without Hetzner account, location, and image.');
        }

        $mapping = VmBundleLocationMapping::query()
            ->with('hetznerServerType')
            ->where('vm_bundle_id', $vm->vm_bundle_id)
            ->where('infrastructure_location_id', $location->id)
            ->where('is_active', true)
            ->first();

        $serverType = $mapping?->hetznerServerType;
        if (! $serverType) {
            throw new RuntimeException('No Hetzner server type is mapped for this VM plan and location.');
        }

        try {
            $shouldStartAfterCreate = (bool) ($this->options['start_after_create'] ?? true);
            $canAutoStart = ! $billingCustomer || ! $wallets->isBelowNegativeThreshold($billingCustomer);
            $payload = [
                'name' => $vm->name,
                'server_type' => $serverType->name,
                'image' => $image->remote_image_id ?: $image->provider_metadata['remote_name'] ?? $image->name,
                'location' => $location->remote_name ?: $location->region,
                'start_after_create' => $shouldStartAfterCreate && $canAutoStart,
                'public_net' => ['enable_ipv4' => true, 'enable_ipv6' => true],
                'labels' => [
                    'panel' => 'aviato',
                    'virtual_machine_id' => (string) $vm->id,
                    'customer_id' => (string) $vm->customer_id,
                ],
            ];

            $userData = $this->hetznerUserData($vm);
            if ($userData !== null) {
                $payload['user_data'] = $userData;
            }

            $create = $hetzner->createServer($account, $payload);
            $server = $create['server'] ?? [];
            $actionId = $create['action']['id'] ?? null;
            $history[] = ['step' => 'create', 'result' => $create, 'at' => now()->toISOString()];

            if ($actionId) {
                $history[] = ['step' => 'create_wait', 'result' => $hetzner->waitForAction($account, $actionId), 'at' => now()->toISOString()];
            }

            $remoteId = (string) ($server['id'] ?? '');
            $server = $remoteId !== '' ? ($hetzner->server($account, $remoteId) ?? $server) : $server;
            $ip = data_get($server, 'public_net.ipv4.ip')
                ?: data_get($server, 'public_net.ipv6.ip');

            $vm->forceFill([
                'remote_id' => $remoteId ?: null,
                'remote_name' => $server['name'] ?? $vm->name,
                'ip_address' => $ip,
                'status' => ($server['status'] ?? null) === 'running' ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'last_started_at' => ($server['status'] ?? null) === 'running' ? now() : null,
                'last_billed_at' => now(),
                'last_seen_at' => now(),
                'remote_state' => [
                    'steps' => $history,
                    'finished_at' => now()->toISOString(),
                    'hetzner_server' => $server,
                ],
            ])->save();
        } catch (Throwable $exception) {
            $hasAttemptsRemaining = $this->hasAttemptsRemaining();

            Log::error('Hetzner VM provisioning failed', [
                'virtual_machine_id' => $vm->id,
                'remote_id' => $vm->remote_id,
                'attempt' => $this->attempts(),
                'will_retry' => $hasAttemptsRemaining,
                'message' => $exception->getMessage(),
            ]);

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => $hasAttemptsRemaining
                    ? VirtualMachine::PROVISION_PENDING
                    : VirtualMachine::PROVISION_FAILED,
                'remote_state' => [
                    'steps' => $history,
                    'error' => $exception->getMessage(),
                    $hasAttemptsRemaining ? 'retrying_at' : 'failed_at' => now()->toISOString(),
                    'attempt' => $this->attempts(),
                ],
            ])->save();

            throw $exception;
        }
    }

    private function hetznerUserData(VirtualMachine $vm): ?string
    {
        $lines = [
            '#cloud-config',
            'hostname: '.$vm->hostname,
            'users:',
            '  - name: '.($vm->login_username ?: 'root'),
            '    groups: users, admin',
            '    shell: /bin/bash',
            '    sudo: ALL=(ALL) NOPASSWD:ALL',
        ];

        if ($vm->ssh_public_key) {
            $lines[] = '    ssh_authorized_keys:';
            foreach (preg_split('/\R/', trim($vm->ssh_public_key)) ?: [] as $key) {
                $key = trim($key);
                if ($key !== '') {
                    $lines[] = '      - '.$key;
                }
            }
        }

        if ($vm->login_password) {
            $lines[] = 'chpasswd:';
            $lines[] = '  expire: false';
            $lines[] = '  users:';
            $lines[] = '    - name: '.($vm->login_username ?: 'root');
            $lines[] = '      password: '.$vm->login_password;
            $lines[] = '      type: text';
            $lines[] = 'ssh_pwauth: true';
        }

        return implode("\n", $lines)."\n";
    }

    private function nextAvailableVmid(ProxmoxService $proxmox, VirtualMachine $vm): int
    {
        $server = $vm->proxmoxServer;

        if (! $server) {
            throw new RuntimeException('Provisioning cannot continue without a Proxmox server.');
        }

        $candidate = (int) ($proxmox->nextVmid($server)['vmid'] ?? 0);
        $remoteVmids = $proxmox->assignedGuestVmids($server, $vm->node);
        $localVmids = VirtualMachine::query()
            ->notDeleted()
            ->where('proxmox_server_id', $server->id)
            ->whereNotNull('vmid')
            ->whereKeyNot($vm->id)
            ->pluck('vmid')
            ->map(fn (mixed $vmid): int => (int) $vmid)
            ->all();

        $usedVmids = array_flip(array_merge($remoteVmids, $localVmids));
        $candidate = max(100, $candidate);

        while (isset($usedVmids[$candidate])) {
            $candidate++;
        }

        return $candidate;
    }

    private function provisioningVmid(ProxmoxService $proxmox, VirtualMachine $vm): int
    {
        if ($vm->vmid && $this->remoteVmMatchesPanelVm($proxmox, $vm, (int) $vm->vmid)) {
            return (int) $vm->vmid;
        }

        return $this->nextAvailableVmid($proxmox, $vm);
    }

    private function remoteVmMatchesPanelVm(ProxmoxService $proxmox, VirtualMachine $vm, int $vmid): bool
    {
        try {
            $config = $proxmox->vmConfig($vm->proxmoxServer, $vm->node, $vmid);
        } catch (Throwable) {
            return false;
        }

        return ($config['name'] ?? null) === $vm->name;
    }

    private function hasAttemptsRemaining(): bool
    {
        return $this->job !== null && $this->attempts() < $this->tries;
    }
}

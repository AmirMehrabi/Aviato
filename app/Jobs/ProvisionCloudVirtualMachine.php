<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionCloudVirtualMachine implements ShouldQueue
{
    use FoundationQueueable;

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

    public function handle(ProxmoxService $proxmox, IpPoolService $ipPools): void
    {
        $vm = VirtualMachine::query()
            ->with(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool'])
            ->findOrFail($this->virtualMachineId);

        $server = $vm->proxmoxServer;
        $image = $vm->cloudImage;
        $address = $vm->reservedIpAddress;

        if (! $server || ! $image) {
            throw new \RuntimeException('Provisioning cannot continue without Proxmox server and cloud image.');
        }

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
            $config = $proxmox->configureCloudInit($server, [
                'node' => $vm->node,
                'vmid' => $vmid,
                'cpu_cores' => $vm->cpu_cores,
                'ram_gb' => $vm->ram_gb,
                'login_username' => $cloudInitEnabled ? $vm->login_username : null,
                'login_password' => $cloudInitEnabled ? $vm->login_password : null,
                'ssh_public_key' => $cloudInitEnabled ? $vm->ssh_public_key : null,
                'ipconfig0' => ($cloudInitEnabled && $address) ? $ipPools->ipConfig($address) : null,
                'nameserver' => ($cloudInitEnabled && $address) ? $ipPools->nameservers($address) : null,
                'cicustom' => $cloudInitEnabled ? 'vendor=local:snippets/ubuntu-password-login.yml' : null,
                'onboot' => $this->options['onboot'] ?? false,
                'description' => $cloudInitEnabled ? 'Cloud-init configured by Aviato panel' : 'Configured by Aviato panel',
            ]);
            $history[] = ['step' => 'config', 'result' => $config];

            $resize = $proxmox->resizeDisk($server, $vm->node, $vmid, $image->disk_device, $vm->disk_gb);
            $history[] = ['step' => 'resize', 'result' => $resize];
            if (! empty($resize['task_id'])) {
                $history[] = ['step' => 'resize_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $resize['task_id'])];
            }

            if ($cloudInitEnabled) {
                $cloudInit = $proxmox->regenerateCloudInit($server, $vm->node, $vmid);
                $history[] = ['step' => 'cloudinit_regenerate', 'result' => $cloudInit];
                if (! empty($cloudInit['task_id'])) {
                    $history[] = ['step' => 'cloudinit_regenerate_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $cloudInit['task_id'])];
                }
            }

            if ($this->options['start_after_create'] ?? true) {
                $start = $proxmox->startVm($server, $vm->node, $vmid);
                $history[] = ['step' => 'start', 'result' => $start];
                if (! empty($start['task_id'])) {
                    $history[] = ['step' => 'start_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $start['task_id'])];
                }
            }

            $history[] = ['step' => 'config_verify', 'result' => $proxmox->vmConfig($server, $vm->node, $vmid)];

            if ($address) {
                $ipPools->assign($address, $vm);
            }

            $vm->forceFill([
                'status' => ($this->options['start_after_create'] ?? true) ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'last_started_at' => ($this->options['start_after_create'] ?? true) ? now() : null,
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

    private function nextAvailableVmid(ProxmoxService $proxmox, VirtualMachine $vm): int
    {
        $server = $vm->proxmoxServer;

        if (! $server) {
            throw new \RuntimeException('Provisioning cannot continue without a Proxmox server.');
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
